<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;
use App\Services\Availability;
use App\Services\PullList;
use App\Services\Warehouse;
use App\Controllers\Concerns\ShowTabs;

final class ShowController extends Controller
{
    use ShowTabs;

    public const STATUSES = [
        'draft' => 'Draft – still planning',
        'confirmed' => 'Confirmed',
        'prepping' => 'Prepping in the shop',
        'out' => 'Out with the show',
        'returned' => 'Returned',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    public function index(): Response
    {
        $view = $this->request->string('view', 'upcoming');
        $q = $this->request->string('q');
        $now = now();
        [$where, $params, $order] = match ($view) {
            'out' => ["status = 'out'", [], 'return_at'],
            'past' => ["(status IN ('returned', 'closed', 'cancelled') OR (return_at < ? AND status <> 'out'))", [$now], 'out_at DESC'],
            'all' => ['1 = 1', [], 'out_at DESC'],
            default => ["status NOT IN ('returned', 'closed', 'cancelled', 'out') AND return_at >= ?", [$now], 'out_at'],
        };
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR reference LIKE ? OR venue LIKE ?)';
            $like = Database::like($q);
            array_push($params, $like, $like, $like);
        }
        $shows = $this->db->all(
            "SELECT s.*, (SELECT COALESCE(SUM(quantity), 0) FROM show_items WHERE show_id = s.id) AS units
             FROM shows s WHERE {$where} ORDER BY {$order} LIMIT 200",
            $params,
        );

        return $this->view('shows/index', [
            'title' => 'Shows',
            'module' => 'shows',
            'tab' => in_array($view, ['out', 'past', 'all'], true) ? $view : 'upcoming',
            'view' => $view,
            'shows' => $shows,
            'q' => $q,
        ]);
    }

    public function create(): Response
    {
        $start = strtotime('monday next week 09:00');

        return $this->view('shows/form', [
            'title' => 'New show',
            'module' => 'shows',
            'tab' => 'upcoming',
            'show' => [
                'title' => '', 'venue' => '', 'department' => 'Theatre', 'status' => 'draft', 'notes' => '',
                'prep_at' => date('Y-m-d\TH:i', $start), 'out_at' => date('Y-m-d\TH:i', $start + 2 * 86400),
                'opens_on' => '', 'closes_on' => '', 'return_at' => date('Y-m-d\TH:i', $start + 16 * 86400),
            ],
        ]);
    }

    public function store(): Response
    {
        $data = $this->validated();
        $id = $this->db->insert('shows', $data + [
            'status' => 'draft',
            'reference' => $this->nextReference(),
            'share_token' => bin2hex(random_bytes(16)),
            'created_by' => $this->userId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Activity::log($this->db, $id, $this->userId(), 'Created the show');
        $this->success('Show created. Now add what it needs to the pull list.');

        return $this->redirect('/shows/' . $id . '/items');
    }

    public function show(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $shortages = array_filter((new Availability($this->db))->forShow($show), static fn ($r) => $r['shortage'] > 0);
        $names = [];
        foreach (array_keys($shortages) as $itemId) {
            $names[$itemId] = $this->db->value('SELECT name FROM items WHERE id = ?', [$itemId]);
        }

        return $this->showView('overview', $show, [
            'summary' => (new Warehouse($this->db))->summary($show),
            'shortages' => $shortages,
            'shortageNames' => $names,
            'team' => $this->team_rows((int) $show['id']),
            'activity' => $this->db->all(
                'SELECT a.*, u.name AS user_name FROM activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.show_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 12',
                [(int) $show['id']],
            ),
        ]);
    }

    public function edit(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        foreach (['prep_at', 'out_at', 'return_at'] as $field) {
            $show[$field] = $show[$field] ? date('Y-m-d\TH:i', strtotime($show[$field])) : '';
        }

        return $this->view('shows/form', ['title' => 'Edit ' . $show['title'], 'module' => 'shows', 'tab' => 'upcoming', 'show' => $show]);
    }

    public function update(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $this->db->update('shows', (int) $show['id'], $this->validated() + ['updated_at' => now()]);
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Updated the show details');
        $this->success('Show updated.');

        return $this->redirect('/shows/' . $show['id']);
    }

    public function destroy(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        if ($this->db->value("SELECT COUNT(*) FROM checkouts WHERE show_id = ? AND status = 'out'", [(int) $show['id']])) {
            $this->error('This show still has equipment out. Return it before deleting the show.');

            return $this->redirect('/shows/' . $show['id']);
        }
        $this->db->transaction(function () use ($show) {
            $this->db->run('DELETE FROM activity WHERE show_id = ?', [(int) $show['id']]);
            $this->db->run('DELETE FROM messages WHERE show_id = ?', [(int) $show['id']]);
            $this->db->run('DELETE FROM documents WHERE show_id = ?', [(int) $show['id']]);
            $this->db->run('DELETE FROM checkouts WHERE show_id = ?', [(int) $show['id']]);
            $this->db->run('DELETE FROM show_containers WHERE show_id = ?', [(int) $show['id']]);
            $this->db->run('DELETE FROM show_items WHERE show_id = ? AND parent_id IS NOT NULL', [(int) $show['id']]);
            $this->db->delete('shows', (int) $show['id']);
        });
        $this->success('Show deleted.');

        return $this->redirect('/shows');
    }

    public function status(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $status = $this->request->string('status');
        $allowed = ['draft', 'confirmed', 'prepping', 'closed', 'cancelled'];
        if (! in_array($status, $allowed, true)) {
            throw new HttpException(422, 'That status cannot be set by hand.');
        }
        if (in_array($status, ['closed', 'cancelled'], true)
            && $this->db->value("SELECT COUNT(*) FROM checkouts WHERE show_id = ? AND status = 'out'", [(int) $show['id']])) {
            $this->error('Equipment is still out with this show. Return it first.');

            return $this->back('/shows/' . $show['id']);
        }
        if (in_array($status, ['closed', 'cancelled'], true)) {
            $this->db->run("DELETE FROM checkouts WHERE show_id = ? AND status = 'prepped'", [(int) $show['id']]);
            $this->db->run("UPDATE show_containers SET status = 'returned', returned_at = ? WHERE show_id = ? AND status = 'prepped'", [now(), (int) $show['id']]);
        }
        $this->db->update('shows', (int) $show['id'], ['status' => $status, 'updated_at' => now()]);
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Changed the status to ' . self::STATUSES[$status]);
        $this->success('Status changed to “' . self::STATUSES[$status] . '”.');

        return $this->back('/shows/' . $show['id']);
    }

    public function share(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $this->db->update('shows', (int) $show['id'], ['share_token' => bin2hex(random_bytes(16)), 'updated_at' => now()]);
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Made a new show portal link (the old link stops working)');
        $this->success('New portal link created. The old link no longer works.');

        return $this->redirect('/shows/' . $show['id'] . '/messages');
    }

    // ---- Pull list ----

    public function items(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $availability = (new Availability($this->db))->forShow($show);
        $items = $this->db->all(
            'SELECT i.id, i.name, i.tracking, c.name AS category_name FROM items i LEFT JOIN categories c ON c.id = i.category_id
             WHERE i.active = 1 ORDER BY c.sort, c.name, i.name',
        );
        $kits = [];
        foreach ($this->db->all('SELECT ia.item_id, ia.quantity, i.name FROM item_accessories ia JOIN items i ON i.id = ia.accessory_id ORDER BY i.name') as $row) {
            $kits[(int) $row['item_id']][] = $row['quantity'] . ' × ' . $row['name'];
        }

        return $this->showView('items', $show, [
            'lines' => (new PullList($this->db))->lines((int) $show['id']),
            'availability' => $availability,
            'items' => $items,
            'kits' => $kits,
            'sections' => $this->db->all('SELECT DISTINCT section FROM show_items WHERE section IS NOT NULL ORDER BY section'),
        ]);
    }

    public function addItem(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $data = $this->validate([
            'item_id' => 'required|int',
            'quantity' => 'required|int|min:1|max:10000',
            'section' => 'max:60',
            'notes' => 'max:500',
        ], ['item_id' => 'Item']);
        $item = $this->findOrFail('items', $data['item_id']);
        (new PullList($this->db))->add((int) $show['id'], (int) $item['id'], $data['quantity'], $data['section'], $data['notes']);
        $kit = (int) $this->db->value('SELECT COUNT(*) FROM item_accessories WHERE item_id = ?', [(int) $item['id']]);
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Added ' . $data['quantity'] . ' × ' . $item['name'] . ' to the pull list');
        $this->success($data['quantity'] . ' × ' . $item['name'] . ' added' . ($kit ? ' with its kit.' : '.'));

        return $this->redirect('/shows/' . $show['id'] . '/items');
    }

    public function updateItem(int|string $id, int|string $line): Response
    {
        $show = $this->findOrFail('shows', $id);
        $row = $this->line($show, $line);
        $data = $this->validate(['quantity' => 'required|int|min:1|max:10000', 'section' => 'max:60', 'notes' => 'max:500']);
        (new PullList($this->db))->setQuantity((int) $row['id'], $data['quantity']);
        $this->db->update('show_items', (int) $row['id'], ['section' => $data['section'], 'notes' => $data['notes'], 'updated_at' => now()]);
        if ($row['parent_id'] === null) {
            $this->db->run('UPDATE show_items SET section = ? WHERE parent_id = ?', [$data['section'], (int) $row['id']]);
        }
        $this->success('Pull list updated.');

        return $this->redirect('/shows/' . $show['id'] . '/items');
    }

    public function removeItem(int|string $id, int|string $line): Response
    {
        $show = $this->findOrFail('shows', $id);
        $row = $this->line($show, $line);
        $this->db->run('DELETE FROM show_items WHERE parent_id = ?', [(int) $row['id']]);
        $this->db->delete('show_items', (int) $row['id']);
        $name = $this->db->value('SELECT name FROM items WHERE id = ?', [(int) $row['item_id']]);
        Activity::log($this->db, (int) $show['id'], $this->userId(), 'Removed ' . $name . ' from the pull list');
        $this->success($name . ' removed from the pull list.');

        return $this->redirect('/shows/' . $show['id'] . '/items');
    }

    // ---- Production team ----

    public function team(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);

        return $this->showView('team', $show, [
            'team' => $this->team_rows((int) $show['id']),
            'contacts' => $this->db->all(
                'SELECT * FROM contacts WHERE id NOT IN (SELECT contact_id FROM show_contacts WHERE show_id = ?) ORDER BY name',
                [(int) $show['id']],
            ),
        ]);
    }

    public function addContact(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $data = $this->validate(['contact_id' => 'int', 'role' => 'max:120', 'name' => 'max:120', 'email' => 'email|max:190', 'phone' => 'max:60']);
        $contactId = $data['contact_id'];
        if (! $contactId) {
            if (! $data['name']) {
                throw new ValidationException(['contact_id' => 'Pick someone from the list or type a new name.']);
            }
            $contactId = $this->db->insert('contacts', [
                'name' => $data['name'], 'role' => $data['role'], 'email' => $data['email'], 'phone' => $data['phone'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $contact = $this->findOrFail('contacts', $contactId);
        if (! $this->db->value('SELECT COUNT(*) FROM show_contacts WHERE show_id = ? AND contact_id = ?', [(int) $show['id'], $contactId])) {
            $this->db->insert('show_contacts', ['show_id' => (int) $show['id'], 'contact_id' => $contactId, 'role' => $data['role'] ?: $contact['role']]);
        }
        $this->success($contact['name'] . ' added to the production team.');

        return $this->redirect('/shows/' . $show['id'] . '/team');
    }

    public function removeContact(int|string $id, int|string $contact): Response
    {
        $show = $this->findOrFail('shows', $id);
        $this->db->run('DELETE FROM show_contacts WHERE show_id = ? AND contact_id = ?', [(int) $show['id'], (int) $contact]);
        $this->success('Removed from the production team.');

        return $this->redirect('/shows/' . $show['id'] . '/team');
    }

    // ---- Messages ----

    public function messages(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);

        return $this->showView('messages', $show, [
            'messages' => $this->db->all(
                'SELECT m.*, d.title AS document_title FROM messages m LEFT JOIN documents d ON d.id = m.document_id
                 WHERE m.show_id = ? ORDER BY m.created_at, m.id',
                [(int) $show['id']],
            ),
            'documents' => $this->db->all('SELECT id, title FROM documents WHERE show_id = ? ORDER BY created_at DESC', [(int) $show['id']]),
        ]);
    }

    public function postMessage(int|string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $data = $this->validate([
            'body' => 'required|max:5000',
            'direction' => 'required|in:shop,internal',
            'document_id' => 'int',
        ], ['body' => 'Message']);
        $documentId = null;
        if ($data['document_id']) {
            $doc = $this->db->one('SELECT * FROM documents WHERE id = ? AND show_id = ?', [$data['document_id'], (int) $show['id']]);
            if ($doc) {
                $documentId = (int) $doc['id'];
                if ($data['direction'] === 'shop' && ! $doc['shared']) {
                    $this->db->update('documents', $documentId, ['shared' => 1, 'updated_at' => now()]);
                }
            }
        }
        $me = $this->app->auth()->user();
        $this->db->insert('messages', [
            'show_id' => (int) $show['id'],
            'user_id' => $this->userId(),
            'author_name' => $me['name'] . ' (Shop)',
            'direction' => $data['direction'],
            'body' => $data['body'],
            'document_id' => $documentId,
            'created_at' => now(),
        ]);
        $this->success($data['direction'] === 'internal' ? 'Internal note saved (the show cannot see it).' : 'Message sent to the show portal.');

        return $this->redirect('/shows/' . $show['id'] . '/messages');
    }

    // ---- Helpers ----

    private function team_rows(int $showId): array
    {
        return $this->db->all(
            'SELECT c.*, sc.role AS show_role FROM show_contacts sc JOIN contacts c ON c.id = sc.contact_id WHERE sc.show_id = ? ORDER BY c.name',
            [$showId],
        );
    }

    private function line(array $show, int|string $line): array
    {
        $row = $this->db->one('SELECT * FROM show_items WHERE id = ? AND show_id = ?', [(int) $line, (int) $show['id']]);
        if (! $row) {
            throw new HttpException(404);
        }

        return $row;
    }

    private function validated(): array
    {
        $data = $this->validate([
            'title' => 'required|max:190',
            'venue' => 'max:190',
            'department' => 'max:60',
            'status' => 'in:draft,confirmed,prepping',
            'prep_at' => 'date',
            'out_at' => 'required|date',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'return_at' => 'required|date',
            'notes' => 'max:5000',
        ], ['out_at' => 'Goes out', 'return_at' => 'Comes back', 'prep_at' => 'Prep starts', 'opens_on' => 'Opening night', 'closes_on' => 'Closing night']);
        if ($data['status'] === null) {
            unset($data['status']);
        }
        foreach (['prep_at', 'out_at', 'return_at'] as $field) {
            $data[$field] = $this->datetime($data[$field]);
        }
        if ($data['return_at'] <= $data['out_at']) {
            throw new ValidationException(['return_at' => 'The return date must be after the date it goes out.']);
        }
        if ($data['prep_at'] && $data['prep_at'] > $data['out_at']) {
            throw new ValidationException(['prep_at' => 'Prep has to start before the equipment goes out.']);
        }
        if ($data['opens_on'] && $data['closes_on'] && $data['closes_on'] < $data['opens_on']) {
            throw new ValidationException(['closes_on' => 'Closing night must be on or after opening night.']);
        }

        return $data;
    }

    private function nextReference(): string
    {
        $prefix = 'S' . date('y') . '-';
        $last = $this->db->value('SELECT reference FROM shows WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1', [$prefix . '%']);
        $n = $last ? (int) substr((string) $last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
