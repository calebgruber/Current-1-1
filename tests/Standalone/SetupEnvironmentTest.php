<?php

// Run with: php tests/Standalone/SetupEnvironmentTest.php
require __DIR__.'/../../app/Domain/Setup/Services/SetupLock.php';
require __DIR__.'/../../app/Domain/Setup/Services/EnvironmentWriter.php';

use App\Domain\Setup\Services\EnvironmentWriter;
use App\Domain\Setup\Services\SetupLock;

function base_path(string $path): string
{
    return __DIR__.'/../../'.$path;
}

if (! function_exists('pcntl_fork')) {
    fwrite(STDERR, "pcntl is required for this concurrency test.\n");
    exit(1);
}

$prefix = __DIR__.'/setup-env-test-'.getmypid();
$path = $prefix.'.env';
$guard = $prefix.'.guard';
$generated = $prefix.'.generated';
$newPath = $prefix.'.new-env';
$results = [];
$children = [];

try {
    file_put_contents($path, "APP_KEY=\n");
    for ($i = 0; $i < 8; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork test process.');
        }
        if ($pid === 0) {
            try {
                $writer = new EnvironmentWriter($path, new SetupLock($guard));
                $key = $writer->ensureAppKey(function () use ($generated): string {
                    file_put_contents($generated, "generated\n", FILE_APPEND);
                    usleep(30000);

                    return 'base64:'.base64_encode(random_bytes(32));
                });
                $writer->set(['WORKER_'.$i => 'done']);
                file_put_contents($prefix.'.'.$i, $key);
                exit(0);
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage()."\n");
                exit(1);
            }
        }
        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            throw new RuntimeException('A concurrent worker failed.');
        }
    }
    $children = [];
    $writer = new EnvironmentWriter($path, new SetupLock($guard));
    for ($i = 0; $i < 8; $i++) {
        $results[] = file_get_contents($prefix.'.'.$i);
        if ($writer->get('WORKER_'.$i) !== 'done') {
            throw new RuntimeException('A concurrent environment update was lost.');
        }
    }
    if (count(array_unique($results)) !== 1 || $writer->get('APP_KEY') !== $results[0] || file_get_contents($generated) !== "generated\n") {
        throw new RuntimeException('Concurrent requests did not share one persisted key.');
    }
    foreach ([0600, 0640] as $mode) {
        chmod($path, $mode);
        $writer->set(['PERMISSION_TEST' => 'saved']);
        clearstatcache(true, $path);
        if ((fileperms($path) & 0777) !== $mode) {
            throw new RuntimeException('An atomic update changed existing .env permissions.');
        }
    }
    $mask = umask(0000);
    try {
        $newWriter = new EnvironmentWriter($newPath, new SetupLock($guard));
        $newWriter->ensureAppKey(fn () => 'new-key');
    } finally {
        umask($mask);
    }
    clearstatcache(true, $newPath);
    if ((fileperms($newPath) & 0777) !== 0600 || $newWriter->get('APP_KEY') !== 'new-key') {
        throw new RuntimeException('A new .env must be persisted with mode 0600.');
    }
    if (glob($path.'.tmp.*') || glob($newPath.'.tmp.*')) {
        throw new RuntimeException('Atomic environment update left a temporary file.');
    }
    $broken = new EnvironmentWriter($prefix.'-missing/.env', new SetupLock($guard));
    try {
        $broken->ensureAppKey(fn () => 'unpersisted-key');
        throw new LogicException('An unpersisted key was accepted.');
    } catch (RuntimeException) {
        // A failed write must not return a transient key.
    }
    echo "Setup environment concurrency checks passed.\n";
} finally {
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    foreach (array_merge([$path, $newPath, $guard, $generated], glob($path.'.tmp.*'), glob($newPath.'.tmp.*'), array_map(fn ($i) => $prefix.'.'.$i, range(0, 7))) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
