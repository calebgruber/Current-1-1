<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLES = [
        'admin' => 'Shop manager (admin)',
        'staff' => 'Staff / crew chief',
        'student' => 'Student crew',
    ];

    /** Abilities each role has. Admins can do everything. */
    private const ABILITIES = [
        'staff' => [
            'inventory.manage', 'shows.manage', 'warehouse', 'messages',
            'documents.generate', 'templates.manage', 'contacts.manage', 'delete',
        ],
        'student' => ['warehouse', 'messages', 'documents.generate'],
    ];

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private readonly Session $session, private readonly ?Database $db)
    {
    }

    public function user(): ?array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $id = (int) $this->session->get('user_id', 0);
            if ($id > 0 && $this->db) {
                $user = $this->db->one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
                $this->user = $user ?: null;
            }
        }

        return $this->user;
    }

    public function id(): ?int
    {
        return isset($this->user()['id']) ? (int) $this->user()['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db?->one('SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND active = 1', [trim($email)]);
        // Always run a hash check so response time does not reveal whether the email exists.
        $hash = $user['password_hash'] ?? password_hash('timing-safe-placeholder', PASSWORD_DEFAULT);
        if (! password_verify($password, $hash) || ! $user) {
            return false;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('users', (int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        $this->login($user);
        $this->db->update('users', (int) $user['id'], ['last_login_at' => now()]);

        return true;
    }

    public function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->put('user_id', (int) $user['id']);
        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->clear();
        $this->session->regenerate();
        $this->user = null;
    }

    public function can(string $ability): bool
    {
        $role = $this->user()['role'] ?? null;
        if ($role === null) {
            return false;
        }
        if ($role === 'admin') {
            return true;
        }

        return in_array($ability, self::ABILITIES[$role] ?? [], true);
    }

    public function refresh(): void
    {
        $this->loaded = false;
        $this->user = null;
    }
}
