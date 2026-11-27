<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Migrator;
use App\Core\Response;
use App\Core\Settings;
use App\Core\ValidationException;
use App\Services\DemoData;
use App\Services\Paperwork;

final class AdminController extends Controller
{
    /** Friendly labels and hints for every shop setting. */
    public const SETTING_FIELDS = [
        'shop_name' => ['Shop name', 'Shown in the top bar and on paperwork.'],
        'shop_department' => ['Department', 'e.g. Lighting & Sound'],
        'institution' => ['College or institution', 'Printed on receipts and paperwork.'],
        'shop_email' => ['Shop email', 'Where productions can reach the shop.'],
        'shop_phone' => ['Shop phone', ''],
        'shop_address' => ['Shop address', 'Printed on paperwork.'],
        'scan_sounds' => ['Play scan sounds by default', 'Everyone can still mute sounds for themselves.'],
        'calendar_months' => ['Months shown on the calendar', 'How far ahead the calendar looks.'],
        'receipt_footer' => ['Receipt footer', 'A short note printed at the bottom of every receipt.'],
    ];

    // ---- Users ----

    public function users(): Response
    {
        return $this->view('admin/users', [
            'title' => 'Users',
            'module' => 'admin',
            'tab' => 'users',
            'users' => $this->db->all('SELECT * FROM users ORDER BY active DESC, name'),
            'meId' => $this->userId(),
        ]);
    }

    public function storeUser(): Response
    {
        $data = $this->validate([
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'role' => 'required|in:' . implode(',', array_keys(Auth::ROLES)),
            'password' => 'required|max:200',
        ]);
        $email = mb_strtolower($data['email']);
        if ($this->emailTaken($email)) {
            throw new ValidationException(['email' => 'Someone already uses that email.']);
        }
        if (mb_strlen($data['password']) < 8) {
            throw new ValidationException(['password' => 'The password must be at least 8 characters.']);
        }
        $this->db->insert('users', [
            'name' => $data['name'],
            'email' => $email,
            'role' => $data['role'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->success($data['name'] . ' can now sign in.');

        return $this->redirect('/admin/users');
    }

    public function updateUser(int|string $id): Response
    {
        $user = $this->findOrFail('users', $id);
        try {
            $data = $this->validate([
                'name' => 'required|max:120',
                'email' => 'required|email|max:190',
                'role' => 'required|in:' . implode(',', array_keys(Auth::ROLES)),
                'active' => 'bool',
                'password' => 'max:200',
            ]);
            $email = mb_strtolower($data['email']);
            if ($this->emailTaken($email, (int) $user['id'])) {
                throw new ValidationException(['email' => 'Someone else already uses that email.']);
            }
            if ($data['password'] !== null && mb_strlen($data['password']) < 8) {
                throw new ValidationException(['password' => 'The new password must be at least 8 characters.']);
            }
        } catch (ValidationException $e) {
            $this->error('Could not save ' . $user['name'] . ': ' . implode(' ', $e->errors));

            return $this->redirect('/admin/users');
        }

        $isMe = (int) $user['id'] === $this->userId();
        if ($isMe && (! $data['active'] || $data['role'] !== 'admin')) {
            $this->error('You cannot deactivate yourself or remove your own admin role. Ask another admin to do it.');

            return $this->redirect('/admin/users');
        }
        $wasActiveAdmin = $user['role'] === 'admin' && (int) $user['active'] === 1;
        $staysActiveAdmin = $data['role'] === 'admin' && $data['active'] === 1;
        if ($wasActiveAdmin && ! $staysActiveAdmin) {
            $others = (int) $this->db->value(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?",
                [(int) $user['id']],
            );
            if ($others === 0) {
                $this->error('There must always be at least one active admin.');

                return $this->redirect('/admin/users');
            }
        }

        $update = [
            'name' => $data['name'],
            'email' => $email,
            'role' => $data['role'],
            'active' => $data['active'],
            'updated_at' => now(),
        ];
        if ($data['password'] !== null) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $this->db->update('users', (int) $user['id'], $update);
        $this->success($data['name'] . ' has been updated.');

        return $this->redirect('/admin/users');
    }

    private function emailTaken(string $email, int $exceptId = 0): bool
    {
        return (bool) $this->db->value('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ?', [$email, $exceptId]);
    }

    // ---- Settings ----

    public function settings(): Response
    {
        return $this->view('admin/settings', [
            'title' => 'Shop settings',
            'module' => 'admin',
            'tab' => 'settings',
            'values' => $this->app->settings()->all(),
            'fields' => self::SETTING_FIELDS,
        ]);
    }

    public function saveSettings(): Response
    {
        $data = $this->validate([
            'shop_name' => 'required|max:120',
            'shop_department' => 'max:120',
            'institution' => 'max:190',
            'shop_email' => 'email|max:190',
            'shop_phone' => 'max:60',
            'shop_address' => 'max:500',
            'scan_sounds' => 'bool',
            'calendar_months' => 'required|int|min:1|max:12',
            'receipt_footer' => 'max:1000',
        ], array_map(static fn ($f) => $f[0], self::SETTING_FIELDS));

        $values = [];
        foreach (array_keys(Settings::DEFAULTS) as $key) {
            $values[$key] = (string) ($data[$key] ?? '');
        }
        $this->app->settings()->set($values);
        $this->success('Settings saved.');

        return $this->redirect('/admin/settings');
    }

    // ---- Database ----

    public function database(): Response
    {
        $migrator = new Migrator($this->db);
        $migrations = $migrator->status();
        $counts = [];
        foreach (['items' => 'Items', 'assets' => 'Serial units', 'shows' => 'Shows', 'documents' => 'Documents', 'users' => 'Users'] as $table => $label) {
            $counts[$label] = $this->db->tableExists($table) ? (int) $this->db->value('SELECT COUNT(*) FROM ' . $this->db->ident($table)) : null;
        }
        $config = (array) $this->app->config('db', []);

        return $this->view('admin/database', [
            'title' => 'Database',
            'module' => 'admin',
            'tab' => 'database',
            'driver' => $this->db->driver,
            'file' => $this->db->driver === 'sqlite' ? basename((string) ($config['path'] ?? 'database.sqlite')) : null,
            'migrations' => $migrations,
            'pending' => count(array_filter($migrations, static fn ($m) => ! $m['ran'])),
            'counts' => $counts,
        ]);
    }

    public function migrate(): Response
    {
        try {
            $ran = (new Migrator($this->db))->migrate();
            $this->success($ran ? 'Migrations ran: ' . implode(', ', $ran) . '.' : 'The database is already up to date.');
        } catch (\Throwable $e) {
            $this->error('A migration failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/database');
    }

    public function seedTemplates(): Response
    {
        try {
            $added = (new Paperwork($this->db, $this->app->settings()))->seedTemplates();
            $this->success($added
                ? 'Reinstalled ' . $added . ' missing paperwork template' . ($added === 1 ? '' : 's') . '.'
                : 'All paperwork templates are already installed.');
        } catch (\Throwable $e) {
            $this->error('Could not install the templates: ' . $e->getMessage());
        }

        return $this->redirect('/admin/database');
    }

    public function demo(): Response
    {
        try {
            $result = (new DemoData($this->db))->load($this->userId());
            if (! empty($result['ok'])) {
                $this->success($result['message'] ?? 'Demo data loaded.');
            } else {
                $this->error($result['message'] ?? 'The demo data could not be loaded.');
            }
        } catch (\Throwable $e) {
            $this->error('Could not load the demo data: ' . $e->getMessage());
        }

        return $this->redirect('/admin/database');
    }
}
