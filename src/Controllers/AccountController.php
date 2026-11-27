<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\ValidationException;

final class AccountController extends Controller
{
    public function show(): Response
    {
        return $this->view('account/index', ['title' => 'My account', 'me' => $this->app->auth()->user()]);
    }

    public function update(): Response
    {
        $data = $this->validate([
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'theme' => 'required|in:auto,light,dark',
            'sound_muted' => 'bool',
        ]);
        $email = mb_strtolower($data['email']);
        if ($this->db->value('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ?', [$email, $this->userId()])) {
            throw new ValidationException(['email' => 'Another account already uses that email.']);
        }
        $this->db->update('users', $this->userId(), array_merge($data, ['email' => $email, 'updated_at' => now()]));
        $this->success('Your account has been updated.');

        return $this->redirect('/account');
    }

    public function password(): Response
    {
        $this->validate(['current_password' => 'required', 'password' => 'required|min:8|max:200'], ['password' => 'New password']);
        $me = $this->app->auth()->user();
        if (! password_verify((string) $this->request->input('current_password'), $me['password_hash'])) {
            throw new ValidationException(['current_password' => 'Your current password is not correct.']);
        }
        if ($this->request->input('password') !== $this->request->input('password_confirmation')) {
            throw new ValidationException(['password_confirmation' => 'The new passwords do not match.']);
        }
        $this->db->update('users', $this->userId(), [
            'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
            'updated_at' => now(),
        ]);
        $this->success('Your password has been changed.');

        return $this->redirect('/account');
    }

    /** Saved in the background by the theme and sound toggles in the top bar. */
    public function preferences(): Response
    {
        $update = [];
        $theme = $this->request->string('theme');
        if (in_array($theme, ['auto', 'light', 'dark'], true)) {
            $update['theme'] = $theme;
        }
        if ($this->request->input('sound_muted') !== null) {
            $update['sound_muted'] = $this->request->bool('sound_muted') ? 1 : 0;
        }
        if ($update) {
            $this->db->update('users', $this->userId(), $update + ['updated_at' => now()]);
        }

        return Response::json(['ok' => true]);
    }
}
