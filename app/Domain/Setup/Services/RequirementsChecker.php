<?php

namespace App\Domain\Setup\Services;

/** Server capability checks shown on the first step of the setup wizard. */
class RequirementsChecker
{
    public const MIN_PHP = '8.2.0';

    /** @return list<array{key: string, label: string, passed: bool, required: bool, detail: string}> */
    public function checks(): array
    {
        $checks = [[
            'key' => 'php',
            'label' => 'PHP '.self::MIN_PHP.' or newer',
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'required' => true,
            'detail' => 'Running PHP '.PHP_VERSION,
        ]];

        $extensions = [
            'pdo_mysql' => [true, 'MySQL / MariaDB database driver'],
            'mbstring' => [true, 'Multibyte string handling'],
            'openssl' => [true, 'Encryption and secure tokens'],
            'zip' => [true, 'Backups and updates'],
            'fileinfo' => [true, 'Upload type detection'],
            'gd' => [false, 'Image processing (labels, logos)'],
            'pdo_sqlite' => [false, 'SQLite driver (optional, for evaluation)'],
            'curl' => [false, 'Outbound HTTP (update feed, webhooks)'],
            'intl' => [false, 'Locale-aware number and date formatting'],
        ];
        foreach ($extensions as $ext => [$required, $why]) {
            $checks[] = [
                'key' => 'ext.'.$ext,
                'label' => $ext.' extension',
                'passed' => extension_loaded($ext),
                'required' => $required,
                'detail' => $why,
            ];
        }

        $paths = [
            'storage' => storage_path(),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'storage/app' => storage_path('app'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        foreach ($paths as $label => $path) {
            $checks[] = [
                'key' => 'writable.'.$label,
                'label' => $label.' is writable',
                'passed' => is_dir($path) && is_writable($path),
                'required' => true,
                'detail' => $path,
            ];
        }

        $env = app()->environmentFilePath();
        $checks[] = [
            'key' => 'writable.env',
            'label' => '.env file is writable',
            'passed' => is_file($env) ? is_writable($env) : is_writable(dirname($env)),
            'required' => true,
            'detail' => is_file($env) ? $env : $env.' (will be created from .env.example)',
        ];

        return $checks;
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && ! $check['passed']) {
                return false;
            }
        }

        return true;
    }
}
