<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ShowTabs;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;
use App\Services\Paperwork;

final class DocumentController extends Controller
{
    use ShowTabs;

    public function index(): Response
    {
        $q = $this->request->string('q');
        $showId = $this->request->int('show_id');
        $where = '1 = 1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (d.title LIKE ? OR s.title LIKE ? OR s.reference LIKE ?)';
            $like = Database::like($q);
            array_push($params, $like, $like, $like);
        }
        if ($showId > 0) {
            $where .= ' AND d.show_id = ?';
            $params[] = $showId;
        }

        return $this->view('documents/index', [
            'title' => 'Paperwork',
            'module' => 'documents',
            'tab' => 'documents',
            'documents' => $this->db->all(
                "SELECT d.id, d.title, d.show_id, d.shared, d.created_at, s.title AS show_title, s.reference AS show_reference, u.name AS user_name
                 FROM documents d LEFT JOIN shows s ON s.id = d.show_id LEFT JOIN users u ON u.id = d.created_by
                 WHERE {$where} ORDER BY d.created_at DESC, d.id DESC LIMIT 300",
                $params,
            ),
            'shows' => $this->db->all('SELECT id, title, reference FROM shows ORDER BY out_at DESC LIMIT 300'),
            'q' => $q,
            'showId' => $showId,
        ]);
    }

    public function forShow(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);

        return $this->showView('documents', $show, [
            'templates' => $this->db->all('SELECT id, template_key, name, description FROM document_templates ORDER BY name'),
            'documents' => $this->db->all(
                'SELECT d.id, d.title, d.shared, d.created_at, u.name AS user_name
                 FROM documents d LEFT JOIN users u ON u.id = d.created_by
                 WHERE d.show_id = ? ORDER BY d.created_at DESC, d.id DESC',
                [(int) $show['id']],
            ),
        ]);
    }

    public function generate(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $data = $this->validate(['template_key' => 'required|max:60'], ['template_key' => 'Paperwork type']);
        $template = $this->db->one('SELECT * FROM document_templates WHERE template_key = ?', [$data['template_key']]);
        if (! $template) {
            throw new ValidationException(['template_key' => 'That paperwork template does not exist.']);
        }
        $docId = (new Paperwork($this->db, $this->app->settings()))->generate($template['template_key'], $show, $this->userId());
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Created paperwork: ' . $template['name']);
        $this->success($template['name'] . ' created.');

        return $this->redirect('/documents/' . $docId);
    }

    public function show(int|string $id): Response
    {
        $document = $this->findOrFail('documents', $id);

        return $this->view('documents/show', [
            'title' => $document['title'],
            'module' => 'documents',
            'tab' => 'documents',
            'document' => $document,
            'show' => $document['show_id'] ? $this->db->find('shows', (int) $document['show_id']) : null,
            'author' => $document['created_by'] ? $this->db->value('SELECT name FROM users WHERE id = ?', [(int) $document['created_by']]) : null,
        ]);
    }

    public function print(int|string $id): Response
    {
        return self::standalone($this->findOrFail('documents', $id));
    }

    public function share(int|string $id): Response
    {
        $document = $this->findOrFail('documents', $id);
        $shared = $document['shared'] ? 0 : 1;
        $this->db->update('documents', (int) $document['id'], ['shared' => $shared, 'updated_at' => now()]);
        Activity::log(
            $this->db,
            $document['show_id'] ? (int) $document['show_id'] : null,
            $this->userId(),
            ($shared ? 'Shared “' : 'Stopped sharing “') . $document['title'] . '” with the show',
        );
        $this->success($shared ? 'The show can now see this on their portal.' : 'This is no longer shared with the show.');

        return $this->back('/documents/' . $document['id']);
    }

    public function destroy(int|string $id): Response
    {
        $document = $this->findOrFail('documents', $id);
        $this->db->run('UPDATE messages SET document_id = NULL WHERE document_id = ?', [(int) $document['id']]);
        $this->db->delete('documents', (int) $document['id']);
        if ($document['show_id']) {
            Activity::log($this->db, (int) $document['show_id'], $this->userId(), 'Deleted paperwork: ' . $document['title']);
        }
        $this->success('Paperwork deleted.');

        return $document['show_id'] ? $this->redirect('/shows/' . $document['show_id'] . '/documents') : $this->redirect('/documents');
    }

    /**
     * A generated document as its own printable page (used by Print and by the show portal).
     *
     * Document bodies come from admin-editable templates, so they must never run script.
     * The Content-Security-Policy blocks every script except our own small print-button
     * script, which carries a fresh random nonce. The nonce is created per response, so a
     * template cannot know it and any <script> inside a template is blocked. Nothing else
     * (fonts, frames, forms, external requests) may load.
     */
    public static function standalone(array $document): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($document['title']) . '</title>'
            . '<style>'
            . 'html,body{margin:0;background:#fff;color:#1b1f24}'
            . '.st-print-bar{display:flex;gap:12px;align-items:center;justify-content:center;padding:10px;background:#f1f3f5;border-bottom:1px solid #dee2e6;font:14px system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}'
            . '.st-print-bar button{font:inherit;font-weight:600;padding:6px 16px;border-radius:6px;border:1px solid #2b5ea7;background:#2b5ea7;color:#fff;cursor:pointer}'
            . '.st-print-bar span{color:#5b6470}'
            . '@media print{.st-print-bar{display:none!important}}'
            . '</style></head><body>'
            . '<div class="st-print-bar"><button type="button" id="st-print-button">Print or save as PDF</button>'
            . '<span>Or press Ctrl+P (Cmd+P on a Mac).</span></div>'
            . '<script nonce="' . e($nonce) . '">document.getElementById("st-print-button").addEventListener("click",function(){window.print();});</script>'
            . $document['body']
            . '</body></html>';

        return Response::html($html)
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; script-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'")
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
