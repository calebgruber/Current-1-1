<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

final class ContactController extends Controller
{
    public function index(): Response
    {
        $q = $this->request->string('q');
        $where = '1 = 1';
        $params = [];
        if ($q !== '') {
            $where = '(c.name LIKE ? OR c.organization LIKE ? OR c.email LIKE ? OR c.role LIKE ?)';
            $like = Database::like($q);
            $params = [$like, $like, $like, $like];
        }
        $contacts = $this->db->all(
            "SELECT c.*, (SELECT COUNT(*) FROM show_contacts sc WHERE sc.contact_id = c.id) AS show_count
             FROM contacts c WHERE {$where} ORDER BY c.name LIMIT 500",
            $params,
        );

        return $this->view('contacts/index', [
            'title' => 'Contacts',
            'module' => 'contacts',
            'tab' => 'contacts',
            'contacts' => $contacts,
            'q' => $q,
        ]);
    }

    public function create(): Response
    {
        return $this->view('contacts/form', [
            'title' => 'New contact',
            'module' => 'contacts',
            'tab' => 'contacts',
            'contact' => ['name' => '', 'organization' => '', 'role' => '', 'email' => '', 'phone' => '', 'notes' => ''],
        ]);
    }

    public function store(): Response
    {
        $id = $this->db->insert('contacts', $this->validated() + ['created_at' => now(), 'updated_at' => now()]);
        $this->success('Contact added.');

        return $this->redirect('/contacts/' . $id);
    }

    public function show(int|string $id): Response
    {
        $contact = $this->findOrFail('contacts', $id);
        $shows = $this->db->all(
            'SELECT s.id, s.title, s.reference, s.status, s.out_at, s.opens_on, sc.role AS show_role
             FROM show_contacts sc JOIN shows s ON s.id = sc.show_id
             WHERE sc.contact_id = ? ORDER BY s.out_at DESC',
            [(int) $contact['id']],
        );

        return $this->view('contacts/show', [
            'title' => $contact['name'],
            'module' => 'contacts',
            'tab' => 'contacts',
            'contact' => $contact,
            'shows' => $shows,
        ]);
    }

    public function update(int|string $id): Response
    {
        $contact = $this->findOrFail('contacts', $id);
        $this->db->update('contacts', (int) $contact['id'], $this->validated() + ['updated_at' => now()]);
        $this->success('Contact updated.');

        return $this->redirect('/contacts/' . $contact['id']);
    }

    public function destroy(int|string $id): Response
    {
        $contact = $this->findOrFail('contacts', $id);
        $this->db->transaction(function () use ($contact) {
            $this->db->run('DELETE FROM show_contacts WHERE contact_id = ?', [(int) $contact['id']]);
            $this->db->delete('contacts', (int) $contact['id']);
        });
        $this->success('Contact deleted.');

        return $this->redirect('/contacts');
    }

    private function validated(): array
    {
        $data = $this->validate([
            'name' => 'required|max:120',
            'organization' => 'max:120',
            'role' => 'max:120',
            'email' => 'email|max:190',
            'phone' => 'max:60',
            'notes' => 'max:5000',
        ]);
        if ($data['email'] !== null) {
            $data['email'] = mb_strtolower($data['email']);
        }

        return $data;
    }
}
