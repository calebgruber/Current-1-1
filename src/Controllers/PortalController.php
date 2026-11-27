<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;

/** The production team's private, no-login page for a show. */
final class PortalController extends Controller
{
    private const RATE_LIMIT = 10;
    private const RATE_WINDOW = 600;

    public function show(string $token): Response
    {
        $show = $this->findShow($token);
        $id = (int) $show['id'];

        return $this->view('portal/show', [
            'title' => $show['title'],
            'width' => 'container-xl',
            'show' => $show,
            'token' => $token,
            'messages' => $this->db->all(
                "SELECT m.id, m.author_name, m.direction, m.body, m.created_at, m.document_id, d.title AS document_title, d.shared AS document_shared
                 FROM messages m LEFT JOIN documents d ON d.id = m.document_id
                 WHERE m.show_id = ? AND m.direction IN ('shop', 'show') ORDER BY m.created_at, m.id",
                [$id],
            ),
            'documents' => $this->db->all(
                'SELECT id, title, created_at FROM documents WHERE show_id = ? AND shared = 1 ORDER BY created_at DESC, id DESC',
                [$id],
            ),
            'name' => (string) $this->app->session()->get('portal_name', ''),
        ], 'layouts/guest');
    }

    public function message(string $token): Response
    {
        $show = $this->findShow($token);
        $session = $this->app->session();
        $now = time();
        $recent = array_values(array_filter(
            (array) $session->get('portal_posts', []),
            static fn ($t) => is_int($t) && $t > $now - self::RATE_WINDOW,
        ));
        if (count($recent) >= self::RATE_LIMIT) {
            $session->put('portal_posts', $recent);
            $this->error('You have sent a lot of messages in a short time. Please wait a few minutes and try again.');

            return $this->redirect('/portal/' . $token);
        }

        try {
            $data = $this->validate([
                'name' => 'required|max:120',
                'message' => 'required|max:4000',
            ], ['name' => 'Your name']);
        } catch (ValidationException $e) {
            // Always come back to the portal, even when the browser sends no Referer.
            $session->flash('_errors', $e->errors);
            $session->flash('_old_input', ['name' => $this->request->string('name'), 'message' => $this->request->string('message')]);
            $this->error('Please fix the highlighted fields.');

            return $this->redirect('/portal/' . $token);
        }

        $this->db->insert('messages', [
            'show_id' => (int) $show['id'],
            'user_id' => null,
            'author_name' => $data['name'],
            'direction' => 'show',
            'body' => $data['message'],
            'document_id' => null,
            'created_at' => now(),
        ]);
        Activity::log($this->db, (int) $show['id'], null, $data['name'] . ' sent a message from the show portal');
        $recent[] = $now;
        $session->put('portal_posts', $recent);
        $session->put('portal_name', $data['name']);
        $this->success('Message sent to the shop. Thanks!');

        return $this->redirect('/portal/' . $token);
    }

    public function document(string $token, int|string $id): Response
    {
        $show = $this->findShow($token);
        $document = $this->db->one(
            'SELECT * FROM documents WHERE id = ? AND show_id = ? AND shared = 1',
            [(int) $id, (int) $show['id']],
        );
        if (! $document) {
            throw new HttpException(404);
        }

        return DocumentController::standalone($document);
    }

    private function findShow(string $token): array
    {
        if (strlen($token) < 20) {
            throw new HttpException(404);
        }
        $show = $this->db->one('SELECT * FROM shows WHERE share_token = ?', [$token]);
        if (! $show || $show['status'] === 'cancelled' || ! hash_equals((string) $show['share_token'], $token)) {
            throw new HttpException(404);
        }

        return $show;
    }
}
