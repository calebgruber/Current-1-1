<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;
use App\Services\Paperwork;
use App\Services\TemplateEngine;

final class TemplateController extends Controller
{
    public function index(): Response
    {
        return $this->view('templates/index', [
            'title' => 'Paperwork templates',
            'module' => 'documents',
            'tab' => 'templates',
            'templates' => $this->db->all(
                'SELECT t.id, t.template_key, t.name, t.description, t.updated_at, u.name AS updated_by_name
                 FROM document_templates t LEFT JOIN users u ON u.id = t.updated_by ORDER BY t.name',
            ),
        ]);
    }

    public function edit(int|string $id): Response
    {
        $template = $this->findOrFail('document_templates', $id);

        return $this->view('templates/edit', [
            'title' => 'Edit ' . $template['name'],
            'module' => 'documents',
            'tab' => 'templates',
            'template' => $template,
            'shows' => $this->db->all('SELECT id, title, reference FROM shows ORDER BY out_at DESC LIMIT 50'),
        ]);
    }

    public function update(int|string $id): Response
    {
        $template = $this->findOrFail('document_templates', $id);
        $data = $this->validate([
            'name' => 'required|max:120',
            'description' => 'max:255',
            'body' => 'required',
        ], ['body' => 'Template']);
        $errors = (new TemplateEngine())->lint($data['body']);
        if ($errors) {
            throw new ValidationException(['body' => 'This template has a problem: ' . implode(' ', array_slice($errors, 0, 3))]);
        }
        $this->db->update('document_templates', (int) $template['id'], [
            'name' => $data['name'],
            'description' => $data['description'],
            'body' => $data['body'],
            'updated_by' => $this->userId(),
            'updated_at' => now(),
        ]);
        Activity::log($this->db, null, $this->userId(), 'Edited the “' . $data['name'] . '” template');
        $this->success('Template saved. New paperwork will use it.');

        return $this->redirect('/templates/' . $template['id']);
    }

    /** Live preview for the editor: returns {html, errors}. */
    public function preview(int|string $id): Response
    {
        $this->findOrFail('document_templates', $id);
        $body = (string) $this->request->input('body', '');
        $errors = (new TemplateEngine())->lint($body);
        if ($errors) {
            return Response::json([
                'html' => '<p style="font-family:system-ui,sans-serif;color:#d63939;padding:1rem">Fix the problem in the template to see the preview.</p>',
                'errors' => $errors,
            ]);
        }

        $show = null;
        $showId = $this->request->int('show_id');
        if ($showId > 0) {
            $show = $this->db->find('shows', $showId);
        }
        $show ??= $this->db->one('SELECT * FROM shows ORDER BY out_at DESC, id DESC LIMIT 1');
        $show ??= $this->sampleShow();

        $paperwork = new Paperwork($this->db, $this->app->settings());
        $context = $paperwork->context($show, $this->userId(), 'Preview — ' . $show['title']);

        return Response::json(['html' => $paperwork->render($body, $context), 'errors' => []]);
    }

    public function reset(int|string $id): Response
    {
        $template = $this->findOrFail('document_templates', $id);
        $this->db->update('document_templates', (int) $template['id'], [
            'body' => Paperwork::defaultBody($template['template_key']),
            'updated_by' => $this->userId(),
            'updated_at' => now(),
        ]);
        Activity::log($this->db, null, $this->userId(), 'Reset the “' . $template['name'] . '” template to the original');
        $this->success('Template reset to the original version.');

        return $this->redirect('/templates/' . $template['id']);
    }

    /** Used for previews before any shows exist. */
    private function sampleShow(): array
    {
        $start = strtotime('monday next week 09:00');

        return [
            'id' => 0,
            'reference' => 'SHOW-0000',
            'title' => 'Sample Show',
            'status' => 'confirmed',
            'venue' => 'Main Stage',
            'department' => 'Theatre',
            'prep_at' => date('Y-m-d H:i:s', $start),
            'out_at' => date('Y-m-d H:i:s', $start + 2 * 86400),
            'opens_on' => date('Y-m-d', $start + 9 * 86400),
            'closes_on' => date('Y-m-d', $start + 12 * 86400),
            'return_at' => date('Y-m-d H:i:s', $start + 14 * 86400),
            'notes' => 'This is sample data. Create a show to preview with real equipment.',
        ];
    }
}
