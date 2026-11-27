<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Migrator;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\DemoData;
use App\Services\Paperwork;

/**
 * First-run wizard: choose the database, create the shop manager account,
 * run the migrations and install the paperwork templates. No command line needed.
 */
final class SetupController extends Controller
{
    public function show(): Response
    {
        $this->guard();

        return $this->view('setup/index', [
            'title' => 'Set up StageTrack',
            'needsDatabase' => ! $this->app->hasDatabase(),
            'checks' => $this->checks(),
            'width' => 'container-narrow',
        ], 'layouts/guest');
    }

    public function store(): Response
    {
        $this->guard();
        $config = $this->app->config();

        if (! $this->app->hasDatabase()) {
            $data = $this->validate([
                'driver' => 'required|in:sqlite,mysql',
                'db_host' => 'max:190',
                'db_port' => 'int|min:1|max:65535',
                'db_database' => 'max:190',
                'db_username' => 'max:190',
            ], ['db_database' => 'Database name', 'db_username' => 'Database user', 'db_host' => 'Database host']);
            $db = ['driver' => $data['driver']];
            if ($data['driver'] === 'mysql') {
                if (! $data['db_database'] || ! $data['db_username']) {
                    throw new ValidationException([
                        'db_database' => $data['db_database'] ? null : 'Database name is required for MySQL.',
                        'db_username' => $data['db_username'] ? null : 'Database user is required for MySQL.',
                    ]);
                }
                $db += [
                    'host' => $data['db_host'] ?: 'localhost',
                    'port' => $data['db_port'] ?: 3306,
                    'database' => $data['db_database'],
                    'username' => $data['db_username'],
                    'password' => (string) $this->request->input('db_password', ''),
                ];
            }
            try {
                new Database($db);
            } catch (\PDOException $e) {
                $this->error('Could not connect to the database: ' . $e->getMessage());

                return $this->redirect('/setup');
            }
            $config['db'] = $db;
        }

        $admin = $this->validate([
            'shop_name' => 'required|max:120',
            'institution' => 'max:120',
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|min:8|max:200',
            'demo' => 'bool',
        ], ['name' => 'Your name', 'email' => 'Your email']);
        if ($this->request->string('password') !== $this->request->string('password_confirmation')) {
            throw new ValidationException(['password_confirmation' => 'The passwords do not match.']);
        }

        $config += ['timezone' => date_default_timezone_get(), 'debug' => false];
        $this->writeConfig($config);
        $this->app->reconfigure($config);
        $db = $this->app->db();

        (new Migrator($db))->migrate();
        if ((int) $db->value('SELECT COUNT(*) FROM users') > 0) {
            throw new HttpException(404);
        }
        $this->app->settings()->set(['shop_name' => $admin['shop_name'], 'institution' => $admin['institution'] ?? '']);
        (new Paperwork($db, $this->app->settings()))->seedTemplates();

        $id = $db->insert('users', [
            'name' => $admin['name'],
            'email' => mb_strtolower($admin['email']),
            'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => 1,
            'theme' => 'auto',
            'sound_muted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($admin['demo']) {
            (new DemoData($db))->load($id);
        }
        $this->app->auth()->login($db->find('users', $id));
        $this->success('Welcome to StageTrack! Your shop is ready.');

        return $this->redirect('/');
    }

    private function guard(): void
    {
        if ($this->app->installed()) {
            throw new HttpException(404);
        }
    }

    private function writeConfig(array $config): void
    {
        $file = $this->app->config('config_path', BASE_PATH . '/storage/config.php');
        unset($config['config_path']);
        $php = "<?php\n\n// Written by the StageTrack setup wizard.\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($file, $php, LOCK_EX) === false) {
            throw new HttpException(500, 'Could not write ' . $file . '. Make the storage folder writable and try again.');
        }
        @chmod($file, 0640);
    }

    private function checks(): array
    {
        $storage = BASE_PATH . '/storage';

        return [
            ['PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), 'You have PHP ' . PHP_VERSION],
            ['PDO SQLite or MySQL driver', extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'), implode(', ', \PDO::getAvailableDrivers())],
            ['Storage folder is writable', is_writable($storage), 'storage/'],
            ['Uploads folder is writable', is_writable($storage . '/uploads'), 'storage/uploads/'],
            ['GD or fileinfo for photos', extension_loaded('fileinfo'), 'fileinfo ' . (extension_loaded('fileinfo') ? 'on' : 'off')],
        ];
    }
}
