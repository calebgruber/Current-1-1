<?php

namespace App\Domain\Setup\Services;

use App\Domain\Authentication\Services\PermissionRegistry;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use App\Support\Installation;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use Throwable;

/** Orchestrates the steps of the web installer. */
class Installer
{
    public function __construct(private readonly EnvironmentWriter $env) {}

    /**
     * @param  array{driver: string, host?: ?string, port?: int|string|null, database: string, username?: ?string, password?: ?string}  $db
     * @return string|null error message, or null when the connection works
     */
    public function testConnection(array $db): ?string
    {
        try {
            $this->connect($db);

            return null;
        } catch (Throwable $e) {
            return $this->cleanError($e->getMessage(), $db['password'] ?? null);
        }
    }

    /** @param array{driver: string, host?: ?string, port?: int|string|null, database: string, username?: ?string, password?: ?string} $db */
    public function saveDatabase(array $db): void
    {
        if ($db['driver'] === 'sqlite') {
            $path = $this->sqlitePath($db['database']);
            $this->env->set([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $path,
            ]);
        } else {
            $this->env->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) ($db['host'] ?? '127.0.0.1'),
                'DB_PORT' => (string) ($db['port'] ?? 3306),
                'DB_DATABASE' => $db['database'],
                'DB_USERNAME' => (string) ($db['username'] ?? ''),
                'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            ]);
        }
        $this->applyRuntimeDatabase($db);
    }

    /** Points the current request at the configured database without reloading the environment. */
    public function applyRuntimeDatabase(array $db): void
    {
        if ($db['driver'] === 'sqlite') {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->sqlitePath($db['database']),
                'database.connections.sqlite.url' => null,
            ]);
        } else {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.url' => null,
                'database.connections.mysql.host' => $db['host'] ?? '127.0.0.1',
                'database.connections.mysql.port' => $db['port'] ?? 3306,
                'database.connections.mysql.database' => $db['database'],
                'database.connections.mysql.username' => $db['username'] ?? '',
                'database.connections.mysql.password' => $db['password'] ?? '',
            ]);
        }
        DB::purge();
    }

    /** @param array{name: string, url: string} $app */
    public function saveApplication(array $app): void
    {
        $values = [
            'APP_NAME' => $app['name'],
            'APP_URL' => rtrim($app['url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
        ];
        if (empty(config('app.key'))) {
            $this->env->ensureAppKey(fn () => $this->generateKey());
        }
        $this->env->set($values);
    }

    public function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher', 'AES-256-CBC')));
    }

    /** @return array{ok: bool, output: string} */
    public function migrate(): array
    {
        try {
            $code = Artisan::call('migrate', ['--force' => true]);

            return ['ok' => $code === 0, 'output' => trim(Artisan::output())];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    /** Syncs the permission registry and runs the base seeders (roles, document templates…). */
    public function seed(): array
    {
        $log = [];
        try {
            $result = app(PermissionRegistry::class)->sync();
            $log[] = sprintf('Permissions synced (%d created, %d updated).', $result['created'] ?? 0, $result['updated'] ?? 0);

            $seeder = 'Database\\Seeders\\DatabaseSeeder';
            if (class_exists($seeder)) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                $log[] = trim(Artisan::output()) ?: 'Base data seeded.';
            }

            return ['ok' => true, 'output' => implode("\n", $log)];
        } catch (Throwable $e) {
            $log[] = $e->getMessage();

            return ['ok' => false, 'output' => implode("\n", $log)];
        }
    }

    /** @param array{organization_name?: ?string, timezone?: ?string, currency?: ?string} $settings */
    public function applySettings(array $settings): void
    {
        $repo = app(SettingsRepository::class);
        $repo->setMany(array_filter([
            'general.organization_name' => $settings['organization_name'] ?? null,
            'general.timezone' => $settings['timezone'] ?? null,
            'general.currency' => $settings['currency'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** @param array{name: string, email: string, username?: ?string, password: string} $data */
    public function createAdministrator(array $data): User
    {
        if (User::withTrashed()->where('is_super_admin', true)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An administrator already exists. Setup cannot create another administrator.',
            ]);
        }

        if (User::withTrashed()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already in use. Choose a new account.',
            ]);
        }
        if (! empty($data['username']) && User::withTrashed()->where('username', $data['username'])->exists()) {
            throw ValidationException::withMessages([
                'username' => 'This username is already in use. Choose a new account.',
            ]);
        }

        $user = new User;
        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
        ]);
        $user->forceFill([
            'is_super_admin' => true,
            'password_changed_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    public function hasAdministrator(): bool
    {
        try {
            return User::withTrashed()->where('is_super_admin', true)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function finish(): void
    {
        $repo = app(SettingsRepository::class);
        if (! $repo->get('system.cron_token')) {
            $repo->set('system.cron_token', Str::random(48));
        }

        $this->env->set([
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ]);

        Installation::markInstalled((string) config('stagetrack.version'));

        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
        } catch (Throwable) {
            // Not fatal: caches are rebuilt on demand.
        }
    }

    public function sqlitePath(string $database): string
    {
        if ($database === ':memory:') {
            return $database;
        }
        $isAbsolute = str_starts_with($database, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $database);

        return $isAbsolute ? $database : database_path($database);
    }

    private function connect(array $db): PDO
    {
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];

        if ($db['driver'] === 'sqlite') {
            $path = $this->sqlitePath($db['database']);
            if ($path !== ':memory:' && ! is_file($path)) {
                if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
                    throw new \RuntimeException('Directory '.dirname($path).' is not writable.');
                }
                touch($path);
            }
            $pdo = new PDO('sqlite:'.$path, null, null, $options);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'] ?? '127.0.0.1', (int) ($db['port'] ?? 3306), $db['database']);
            $pdo = new PDO($dsn, $db['username'] ?? '', $db['password'] ?? '', $options);
        }
        $pdo->query('SELECT 1');

        return $pdo;
    }

    private function cleanError(string $message, ?string $password): string
    {
        if ($password) {
            $message = str_replace($password, '••••', $message);
        }

        return Str::limit($message, 300);
    }
}
