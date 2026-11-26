<?php

// Run with: php tests/Standalone/SetupRequirementsTest.php
require __DIR__.'/../../app/Domain/Setup/Services/SetupLock.php';
require __DIR__.'/../../app/Domain/Setup/Services/EnvironmentWriter.php';
require __DIR__.'/../../app/Domain/Setup/Services/RequirementsChecker.php';

use App\Domain\Setup\Services\EnvironmentWriter;
use App\Domain\Setup\Services\RequirementsChecker;
use App\Domain\Setup\Services\SetupLock;

$fixture = __DIR__;
$prefix = $fixture.'/setup-check-'.getmypid();
$env = $prefix.'.env';
$guard = $prefix.'.guard';
$testEnv = $env;
$testGuard = $guard;

function base_path(string $path = ''): string
{
    return __DIR__.'/../../'.$path;
}

function storage_path(string $path = ''): string
{
    global $guard;

    return $path === 'app/setup.guard' ? $guard : __DIR__.'/../../storage/'.$path;
}

function app(): object
{
    global $env;

    return new class($env) {
        public function __construct(private string $env) {}

        public function environmentFilePath(): string
        {
            return $this->env;
        }
    };
}

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function checks(): array
{
    return array_column((new RequirementsChecker)->checks(), null, 'key');
}

try {
    verify(! file_exists($guard), 'Fixture guard must not exist before the test.');
    file_put_contents($env, "APP_KEY=test\n");
    chmod($env, 0600);
    $writer = new EnvironmentWriter($env, new SetupLock($guard));
    verify($writer->writable(), 'Writable environment should pass.');
    verify(checks()['writable.env']['passed'], 'Environment requirement should pass.');
    verify(checks()['writable.setup_guard']['passed'], 'Missing guard in writable directory should pass.');

    file_put_contents($guard, '');
    chmod($guard, 0400);
    clearstatcache();
    if (! is_writable($guard)) {
        verify(! (new SetupLock($guard))->writable(), 'Read-only existing guard should fail.');
        verify(! checks()['writable.setup_guard']['passed'], 'Guard requirement should fail for read-only file.');
    } else {
        echo "Skipping read-only file checks: this user bypasses file permissions.\n";
    }
    chmod($guard, 0600);

    chmod($env, 0400);
    clearstatcache();
    if (! is_writable($env)) {
        verify(! $writer->writable(), 'Read-only existing .env should fail.');
    }
    chmod($env, 0600);

    file_put_contents($env.'.tmp', '');
    chmod($env.'.tmp', 0400);
    clearstatcache();
    verify($writer->writable(), 'An unrelated stale .tmp file should not block unique atomic writes.');
    unlink($env.'.tmp');

    $readOnlyDirectoryFile = '/proc/self/oom_score_adj';
    if (is_file($readOnlyDirectoryFile) && is_readable($readOnlyDirectoryFile)
        && is_writable($readOnlyDirectoryFile) && ! is_writable(dirname($readOnlyDirectoryFile))) {
        $env = $readOnlyDirectoryFile;
        $guard = '/proc/self/setup-guard-does-not-exist';
        verify(! (new EnvironmentWriter($env, new SetupLock($guard)))->writable(), 'Writable .env in read-only directory should fail.');
        verify(! checks()['writable.env']['passed'], 'Environment requirement must check the directory.');
        verify(! (new SetupLock($guard))->writable(), 'Missing guard in read-only directory should fail.');
        verify(! checks()['writable.setup_guard']['passed'], 'Guard requirement should check the directory.');
    } else {
        echo "Skipping read-only directory checks: no suitable /proc fixture.\n";
    }
    verify(! (new EnvironmentWriter($fixture, new SetupLock($guard)))->writable(), 'A directory is not an environment file.');
    verify(! (new SetupLock($fixture))->writable(), 'A directory is not a guard file.');
    $env = $testEnv;
    $guard = $testGuard;
    unlink($env);
    unlink($guard);
    clearstatcache();
    verify($writer->writable(), 'Missing .env in writable directory should pass.');
    verify((new SetupLock($guard))->writable(), 'Missing guard in writable directory should pass.');
    echo "Setup requirements checks passed.\n";
} finally {
    foreach ([$testEnv, $testEnv.'.tmp', $testGuard] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
