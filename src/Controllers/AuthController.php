<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;

final class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public function show(): Response
    {
        return $this->view('auth/login', ['title' => 'Sign in'], 'layouts/guest');
    }

    public function login(): Response
    {
        $data = $this->validate(['email' => 'required|email', 'password' => 'required']);
        $session = $this->app->session();
        $attempts = $session->get('login_attempts', ['count' => 0, 'until' => 0]);
        if ($attempts['until'] > time()) {
            $this->error('Too many attempts. Wait a few minutes and try again.');

            return $this->redirect('/login');
        }

        if (! $this->app->auth()->attempt($data['email'], (string) $this->request->input('password'))) {
            $count = $attempts['count'] + 1;
            $session->put('login_attempts', [
                'count' => $count >= self::MAX_ATTEMPTS ? 0 : $count,
                'until' => $count >= self::MAX_ATTEMPTS ? time() + self::LOCK_SECONDS : 0,
            ]);
            $session->flash('_old_input', ['email' => $data['email']]);
            $this->error('That email and password do not match an active account.');

            return $this->redirect('/login');
        }
        $session->forget('login_attempts');
        $intended = (string) $session->pull('intended', '/');

        return $this->redirect(str_starts_with($intended, '/') && ! str_starts_with($intended, '//') ? $intended : '/');
    }

    public function logout(): Response
    {
        $this->app->auth()->logout();
        $this->success('You have signed out.');

        return $this->redirect('/login');
    }
}
