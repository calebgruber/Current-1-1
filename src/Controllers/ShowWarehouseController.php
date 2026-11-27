<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ShowTabs;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Activity;
use App\Services\Warehouse;

/** Prep (packing into containers), check out and returns for one show. */
final class ShowWarehouseController extends Controller
{
    use ShowTabs;

    public function prep(string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $warehouse = new Warehouse($this->db);
        $summary = $warehouse->summary($show);

        $loose = $this->db->all(
            "SELECT ch.*, i.name AS item_name, a.asset_tag FROM checkouts ch JOIN items i ON i.id = ch.item_id
             LEFT JOIN assets a ON a.id = ch.asset_id
             WHERE ch.show_id = ? AND ch.container_id IS NULL ORDER BY i.name, a.asset_tag",
            [(int) $show['id']],
        );

        return $this->showView('prep', $show, [
            'summary' => $summary,
            'progress' => $warehouse->progress($show),
            'loose' => $loose,
            'availableContainers' => $this->db->all(
                "SELECT * FROM containers WHERE active = 1 AND id NOT IN
                    (SELECT container_id FROM show_containers WHERE status <> 'returned')
                 ORDER BY name",
            ),
            'bulkItems' => $this->db->all("SELECT id, name FROM items WHERE active = 1 AND tracking = 'bulk' ORDER BY name"),
            'canEdit' => in_array($show['status'], ['draft', 'confirmed', 'prepping', 'out'], true),
        ]);
    }

    public function prepScan(string $id): Response
    {
        $show = $this->editableShow($id);
        $code = $this->request->string('code');
        if ($code === '') {
            return $this->result($show, ['ok' => false, 'message' => 'Scan or type a barcode first.'], 'prep');
        }
        $result = (new Warehouse($this->db))->prepCode(
            $show,
            $code,
            max(1, $this->request->int('quantity', 1)),
            $this->request->int('container_id') ?: null,
            $this->userId(),
        );
        $this->markPrepping($show, $result);

        return $this->result($show, $result, 'prep');
    }

    /** Pick a bulk item and quantity by hand (no barcode needed). */
    public function prepPick(string $id): Response
    {
        $show = $this->editableShow($id);
        $data = $this->validate(['item_id' => 'required|int', 'quantity' => 'required|int|min:1|max:10000'], ['item_id' => 'Item']);
        $result = (new Warehouse($this->db))->prepItem($show, $data['item_id'], $data['quantity'], $this->request->int('container_id') ?: null, $this->userId());
        $this->markPrepping($show, $result);

        return $this->result($show, $result, 'prep');
    }

    public function unprep(string $id, string $checkout): Response
    {
        $show = $this->findOrFail('shows', $id);

        return $this->result($show, (new Warehouse($this->db))->removePrepped($show, (int) $checkout), 'prep');
    }

    public function addContainer(string $id): Response
    {
        $show = $this->editableShow($id);
        $data = $this->validate(['container_id' => 'required|int', 'return_after_transport' => 'bool'], ['container_id' => 'Container']);
        $result = (new Warehouse($this->db))->addContainer($show, $data['container_id'], (bool) $data['return_after_transport']);
        if ($result['ok']) {
            // The flag only applies when first adding; make sure it matches what was picked.
            $this->db->run(
                'UPDATE show_containers SET return_after_transport = ? WHERE show_id = ? AND container_id = ?',
                [$data['return_after_transport'], (int) $show['id'], $data['container_id']],
            );
            $result['container_name'] = $this->db->value('SELECT name FROM containers WHERE id = ?', [$data['container_id']]);
        }

        return $this->result($show, $result, 'prep');
    }

    /** Toggle whether a container comes straight back after transport. */
    public function updateContainer(string $id, string $sc): Response
    {
        $show = $this->findOrFail('shows', $id);
        $row = $this->showContainer($show, $sc);
        $this->db->update('show_containers', (int) $row['id'], [
            'return_after_transport' => $this->request->bool('return_after_transport') ? 1 : 0,
            'updated_at' => now(),
        ]);
        $this->success('Container updated.');

        return $this->redirect('/shows/' . $show['id'] . '/prep');
    }

    public function removeContainer(string $id, string $sc): Response
    {
        $show = $this->findOrFail('shows', $id);
        $row = $this->showContainer($show, $sc);
        if ($row['status'] !== 'prepped') {
            $this->error('This container has already gone out. Return it on the Returns tab instead.');

            return $this->redirect('/shows/' . $show['id'] . '/prep');
        }
        $this->db->transaction(function () use ($show, $row) {
            // Anything still packed inside becomes loose rather than disappearing.
            $this->db->run(
                "UPDATE checkouts SET container_id = NULL WHERE show_id = ? AND container_id = ? AND status = 'prepped'",
                [(int) $show['id'], (int) $row['container_id']],
            );
            $this->db->delete('show_containers', (int) $row['id']);
        });
        $this->success('Container removed. Anything inside is now listed as loose.');

        return $this->redirect('/shows/' . $show['id'] . '/prep');
    }

    public function checkout(string $id): Response
    {
        $show = $this->editableShow($id);
        $result = (new Warehouse($this->db))->checkOut($show);
        if ($result['ok']) {
            Activity::log($this->db, (int) $show['id'], $this->userId(), $result['message']);
        }

        return $this->result($show, $result, 'prep');
    }

    public function returns(string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $summary = (new Warehouse($this->db))->summary($show);

        return $this->showView('returns', $show, [
            'summary' => $summary,
            'outstanding' => $this->db->all(
                "SELECT ch.*, i.name AS item_name, i.tracking, a.asset_tag, c.name AS container_name
                 FROM checkouts ch JOIN items i ON i.id = ch.item_id
                 LEFT JOIN assets a ON a.id = ch.asset_id
                 LEFT JOIN containers c ON c.id = ch.container_id
                 WHERE ch.show_id = ? AND ch.status = 'out' ORDER BY i.name, a.asset_tag",
                [(int) $show['id']],
            ),
            'returned' => $this->db->all(
                "SELECT ch.*, i.name AS item_name, a.asset_tag, u.name AS returned_by_name
                 FROM checkouts ch JOIN items i ON i.id = ch.item_id
                 LEFT JOIN assets a ON a.id = ch.asset_id
                 LEFT JOIN users u ON u.id = ch.returned_by
                 WHERE ch.show_id = ? AND ch.returned_quantity > 0 ORDER BY ch.returned_at DESC, ch.id DESC",
                [(int) $show['id']],
            ),
        ]);
    }

    public function returnScan(string $id): Response
    {
        $show = $this->findOrFail('shows', $id);
        $code = $this->request->string('code');
        if ($code === '') {
            return $this->result($show, ['ok' => false, 'message' => 'Scan or type a barcode first.'], 'returns');
        }
        $result = (new Warehouse($this->db))->returnCode(
            $show,
            $code,
            max(1, $this->request->int('quantity', 1)),
            max(0, $this->request->int('damaged')),
            $this->request->string('note') ?: null,
            $this->userId(),
        );

        return $this->result($show, $result, 'returns');
    }

    public function returnLine(string $id, string $checkout): Response
    {
        $show = $this->findOrFail('shows', $id);
        $data = $this->validate([
            'quantity' => 'required|int|min:1',
            'damaged' => 'int|min:0',
            'note' => 'max:500',
        ], ['quantity' => 'Quantity returned', 'damaged' => 'Damaged']);
        $result = (new Warehouse($this->db))->returnLine($show, (int) $checkout, $data['quantity'], (int) $data['damaged'], $data['note'], $this->userId());

        return $this->result($show, $result, 'returns');
    }

    public function returnContainer(string $id, string $sc): Response
    {
        $show = $this->findOrFail('shows', $id);
        $row = $this->showContainer($show, $sc);

        return $this->result($show, (new Warehouse($this->db))->returnContainer($show, (int) $row['container_id']), 'returns');
    }

    // ---- Helpers ----

    private function editableShow(string $id): array
    {
        $show = $this->findOrFail('shows', $id);
        if (in_array($show['status'], ['returned', 'closed', 'cancelled'], true)) {
            throw new HttpException(422, 'This show is ' . $show['status'] . '. Reopen it (set the status back to Prepping) before packing.');
        }

        return $show;
    }

    private function markPrepping(array $show, array $result): void
    {
        if ($result['ok'] && in_array($show['status'], ['draft', 'confirmed'], true)) {
            $this->db->update('shows', (int) $show['id'], ['status' => 'prepping', 'updated_at' => now()]);
        }
    }

    private function showContainer(array $show, string $sc): array
    {
        $row = $this->db->one('SELECT * FROM show_containers WHERE id = ? AND show_id = ?', [(int) $sc, (int) $show['id']]);
        if (! $row) {
            throw new HttpException(404);
        }

        return $row;
    }

    /** JSON for the scan boxes, or flash + redirect for normal form posts. */
    private function result(array $show, array $result, string $tab): Response
    {
        if ($this->request->wantsJson()) {
            return Response::json($result, $result['ok'] ? 200 : 422);
        }
        $result['ok'] ? $this->success($result['message']) : $this->error($result['message']);

        return $this->redirect('/shows/' . $show['id'] . '/' . $tab);
    }
}
