<?php

namespace App\Domain\Setup\Services;

/** Server capability checks shown on the first step of the setup wizard. */
class RequirementsChecker
{
    public const MIN_PHP = '8.3.0';

    /** @return list<array{key: string, label: string, passed: bool, required: bool, detail: string}> */
    public function checks(): array
    {
        $checks = [[
            'key' => 'php',
            'label' => 'PHP '.self::MIN_PHP.' or newer',
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'required' => true,
            'detail' => 'Running PHP '.PHP_VERSION,
        ], [
            'key' => 'ext.database_driver',
            'label' => 'MySQL / MariaDB or SQLite database driver',
            'passed' => extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
            'required' => true,
            'detail' => 'At least one supported PDO driver is required.',
        ]];

        $extensions = [
            'pdo_mysql' => [false, 'MySQL / MariaDB database driver'],
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
        $writer = new EnvironmentWriter($env);
        $checks[] = [
            'key' => 'writable.env',
            'label' => '.env can be read and atomically replaced',
            'passed' => $writer->writable(),
            'required' => true,
            'detail' => $env.' (file and parent directory must be accessible)',
        ];
        $lock = new SetupLock;
        $checks[] = [
            'key' => 'writable.setup_guard',
            'label' => 'Setup guard file is writable',
            'passed' => $lock->writable(),
            'required' => true,
            'detail' => $lock->path().' (or its parent directory if the file does not exist)',
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
