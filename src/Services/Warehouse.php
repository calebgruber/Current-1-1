<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Prep, check-out and return workflow for a show.
 *
 * Every method returns ['ok' => bool, 'message' => string, ...] so the same code
 * drives the normal pages, the fullscreen scan station and the JSON scan endpoint.
 */
final class Warehouse
{
    public function __construct(private readonly Database $db)
    {
    }

    public function prepCode(array $show, string $code, int $quantity, ?int $containerId, ?int $userId): array
    {
        $found = (new Scanner($this->db))->lookup($code);
        if ($found === null) {
            return $this->fail('Nothing in the shop has the barcode "' . $code . '".');
        }

        return match ($found['type']) {
            'container' => $this->addContainer($show, $found['id'], false),
            'asset' => $this->prepAsset($show, $found['id'], $containerId, $userId),
            'item' => $found['record']['tracking'] === 'serial'
                ? $this->fail($found['label'] . ' is tracked by serial number. Scan the asset tag on the unit instead.')
                : $this->prepItem($show, $found['id'], $quantity, $containerId, $userId),
            default => $this->fail('"' . $found['label'] . '" is a ' . $found['type'] . ', not equipment.'),
        };
    }

    public function addContainer(array $show, int $containerId, bool $returnAfterTransport): array
    {
        $container = $this->db->find('containers', $containerId);
        if (! $container) {
            return $this->fail('That container does not exist.');
        }
        $busy = $this->db->one(
            "SELECT s.reference FROM show_containers sc JOIN shows s ON s.id = sc.show_id
             WHERE sc.container_id = ? AND sc.status <> 'returned' AND sc.show_id <> ?",
            [$containerId, (int) $show['id']],
        );
        if ($busy) {
            return $this->fail($container['name'] . ' is still out with ' . $busy['reference'] . '. Return it there first.');
        }
        $existing = $this->db->one('SELECT * FROM show_containers WHERE show_id = ? AND container_id = ?', [(int) $show['id'], $containerId]);
        if ($existing) {
            if ($existing['status'] === 'returned') {
                $this->db->update('show_containers', (int) $existing['id'], ['status' => 'prepped', 'returned_at' => null, 'updated_at' => now()]);
            }

            return $this->ok('Now packing into ' . $container['name'] . '.', ['container_id' => $containerId]);
        }
        $this->db->insert('show_containers', [
            'show_id' => (int) $show['id'],
            'container_id' => $containerId,
            'return_after_transport' => $returnAfterTransport ? 1 : 0,
            'status' => 'prepped',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok($container['name'] . ' added to the show. Now packing into it.', ['container_id' => $containerId]);
    }

    public function prepAsset(array $show, int $assetId, ?int $containerId, ?int $userId): array
    {
        $asset = $this->db->one(
            'SELECT a.*, i.name AS item_name FROM assets a JOIN items i ON i.id = a.item_id WHERE a.id = ?',
            [$assetId],
        );
        if (! $asset) {
            return $this->fail('That asset does not exist.');
        }
        $label = $asset['item_name'] . ' ' . $asset['asset_tag'];
        if (in_array($asset['status'], ['repair', 'retired', 'missing'], true)) {
            return $this->fail($label . ' is marked "' . $asset['status'] . '" and cannot go out.');
        }
        $active = $this->db->one(
            "SELECT c.*, s.reference FROM checkouts c JOIN shows s ON s.id = c.show_id
             WHERE c.asset_id = ? AND c.status IN ('prepped', 'out')",
            [$assetId],
        );
        if ($active) {
            return (int) $active['show_id'] === (int) $show['id']
                ? $this->fail($label . ' is already packed for this show.')
                : $this->fail($label . ' is already with ' . $active['reference'] . '.');
        }
        $containerId = $this->validContainer($show, $containerId);
        $this->db->insert('checkouts', [
            'show_id' => (int) $show['id'],
            'item_id' => (int) $asset['item_id'],
            'asset_id' => $assetId,
            'container_id' => $containerId,
            'quantity' => 1,
            'status' => 'prepped',
            'prepped_by' => $userId,
            'prepped_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok($this->packedMessage($label, $containerId) . $this->pullListNote($show, (int) $asset['item_id']));
    }

    public function prepItem(array $show, int $itemId, int $quantity, ?int $containerId, ?int $userId): array
    {
        $item = $this->db->find('items', $itemId);
        if (! $item) {
            return $this->fail('That item does not exist.');
        }
        if ($item['tracking'] === 'serial') {
            return $this->fail($item['name'] . ' is tracked by serial number. Scan or choose a specific unit.');
        }
        $quantity = max(1, $quantity);
        $containerId = $this->validContainer($show, $containerId);

        $existing = $this->db->one(
            "SELECT * FROM checkouts WHERE show_id = ? AND item_id = ? AND status = 'prepped' AND "
            . ($containerId === null ? 'container_id IS NULL' : 'container_id = ?'),
            $containerId === null ? [(int) $show['id'], $itemId] : [(int) $show['id'], $itemId, $containerId],
        );
        if ($existing) {
            $this->db->update('checkouts', (int) $existing['id'], [
                'quantity' => (int) $existing['quantity'] + $quantity,
                'updated_at' => now(),
            ]);
        } else {
            $this->db->insert('checkouts', [
                'show_id' => (int) $show['id'],
                'item_id' => $itemId,
                'container_id' => $containerId,
                'quantity' => $quantity,
                'status' => 'prepped',
                'prepped_by' => $userId,
                'prepped_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $label = $quantity . ' × ' . $item['name'];

        return $this->ok($this->packedMessage($label, $containerId) . $this->pullListNote($show, $itemId));
    }

    public function removePrepped(array $show, int $checkoutId): array
    {
        $row = $this->db->one("SELECT * FROM checkouts WHERE id = ? AND show_id = ? AND status = 'prepped'", [$checkoutId, (int) $show['id']]);
        if (! $row) {
            return $this->fail('Only packed items that have not gone out yet can be removed.');
        }
        $this->db->delete('checkouts', $checkoutId);

        return $this->ok('Removed from the packed list.');
    }

    /** Send everything that has been packed out to the show. */
    public function checkOut(array $show): array
    {
        $prepped = $this->db->all("SELECT * FROM checkouts WHERE show_id = ? AND status = 'prepped'", [(int) $show['id']]);
        if ($prepped === []) {
            return $this->fail('Nothing is packed yet. Scan items on the Prep tab first.');
        }

        $this->db->transaction(function () use ($show, $prepped) {
            foreach ($prepped as $row) {
                $this->db->update('checkouts', (int) $row['id'], ['status' => 'out', 'out_at' => now(), 'updated_at' => now()]);
                if ($row['asset_id'] !== null) {
                    $this->db->update('assets', (int) $row['asset_id'], ['status' => 'out', 'updated_at' => now()]);
                }
            }
            $this->db->run(
                "UPDATE show_containers SET status = 'out', updated_at = ? WHERE show_id = ? AND status = 'prepped'",
                [now(), (int) $show['id']],
            );
            $this->db->update('shows', (int) $show['id'], ['status' => 'out', 'updated_at' => now()]);
        });

        $units = array_sum(array_column($prepped, 'quantity'));

        return $this->ok($units . ' ' . ($units === 1 ? 'unit' : 'units') . ' checked out to ' . $show['title'] . '.', ['units' => $units]);
    }

    public function returnCode(array $show, string $code, int $quantity, int $damaged, ?string $note, ?int $userId): array
    {
        $found = (new Scanner($this->db))->lookup($code);
        if ($found === null) {
            return $this->fail('Nothing in the shop has the barcode "' . $code . '".');
        }

        if ($found['type'] === 'container') {
            return $this->returnContainer($show, $found['id']);
        }
        if ($found['type'] === 'asset') {
            $row = $this->db->one(
                "SELECT * FROM checkouts WHERE show_id = ? AND asset_id = ? AND status = 'out'",
                [(int) $show['id'], $found['id']],
            );
            if (! $row) {
                return $this->fail($found['label'] . ' is not out with this show.');
            }

            return $this->returnLine($show, (int) $row['id'], 1, min(1, $damaged), $note, $userId);
        }
        if ($found['type'] === 'item') {
            return $this->returnBulk($show, $found['id'], max(1, $quantity), max(0, $damaged), $note, $userId);
        }

        return $this->fail('"' . $found['label'] . '" is a ' . $found['type'] . ', not equipment.');
    }

    public function returnContainer(array $show, int $containerId): array
    {
        $row = $this->db->one(
            "SELECT sc.*, c.name FROM show_containers sc JOIN containers c ON c.id = sc.container_id
             WHERE sc.show_id = ? AND sc.container_id = ? AND sc.status <> 'returned'",
            [(int) $show['id'], $containerId],
        );
        if (! $row) {
            return $this->fail('That container is not out with this show.');
        }
        $this->db->update('show_containers', (int) $row['id'], ['status' => 'returned', 'returned_at' => now(), 'updated_at' => now()]);
        $this->finishIfComplete($show);

        return $this->ok($row['name'] . ' is back in the shop.');
    }

    /** Return a quantity of a bulk item, spread across the show's outstanding lines. */
    public function returnBulk(array $show, int $itemId, int $quantity, int $damaged, ?string $note, ?int $userId): array
    {
        $rows = $this->db->all(
            "SELECT * FROM checkouts WHERE show_id = ? AND item_id = ? AND status = 'out' AND asset_id IS NULL ORDER BY id",
            [(int) $show['id'], $itemId],
        );
        $outstanding = array_sum(array_map(static fn ($r) => (int) $r['quantity'] - (int) $r['returned_quantity'], $rows));
        $item = $this->db->find('items', $itemId);
        if ($outstanding === 0) {
            return $this->fail(($item['name'] ?? 'That item') . ' is not out with this show.');
        }
        if ($quantity > $outstanding) {
            return $this->fail('Only ' . $outstanding . ' × ' . $item['name'] . ' are still out with this show.');
        }
        $damaged = min($damaged, $quantity);
        $left = $quantity;
        foreach ($rows as $row) {
            if ($left === 0) {
                break;
            }
            $open = (int) $row['quantity'] - (int) $row['returned_quantity'];
            $take = min($open, $left);
            if ($take > 0) {
                $this->returnLine($show, (int) $row['id'], $take, min($damaged, $take), $note, $userId, false);
                $damaged -= min($damaged, $take);
                $left -= $take;
            }
        }
        $this->finishIfComplete($show);

        return $this->ok($quantity . ' × ' . $item['name'] . ' returned.' . ($outstanding - $quantity > 0 ? ' ' . ($outstanding - $quantity) . ' still out.' : ''));
    }

    public function returnLine(array $show, int $checkoutId, int $quantity, int $damaged, ?string $note, ?int $userId, bool $finish = true): array
    {
        $row = $this->db->one(
            "SELECT c.*, i.name AS item_name, a.asset_tag FROM checkouts c JOIN items i ON i.id = c.item_id
             LEFT JOIN assets a ON a.id = c.asset_id WHERE c.id = ? AND c.show_id = ? AND c.status = 'out'",
            [$checkoutId, (int) $show['id']],
        );
        if (! $row) {
            return $this->fail('That line is not out with this show.');
        }
        $open = (int) $row['quantity'] - (int) $row['returned_quantity'];
        $quantity = max(1, min($quantity, $open));
        $damaged = max(0, min($damaged, $quantity));
        $returned = (int) $row['returned_quantity'] + $quantity;
        $notes = trim(($row['notes'] ?? '') . ($note ? "\n" . $note : ''));

        $this->db->update('checkouts', $checkoutId, [
            'returned_quantity' => $returned,
            'damaged_quantity' => (int) $row['damaged_quantity'] + $damaged,
            'status' => $returned >= (int) $row['quantity'] ? 'returned' : 'out',
            'returned_at' => now(),
            'returned_by' => $userId,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => now(),
        ]);
        if ($row['asset_id'] !== null) {
            $assetUpdate = ['status' => $damaged > 0 ? 'repair' : 'available', 'updated_at' => now()];
            if ($damaged > 0 && $note) {
                $assetUpdate['notes'] = trim(($this->db->value('SELECT notes FROM assets WHERE id = ?', [(int) $row['asset_id']]) ?? '') . "\nDamage (" . $show['reference'] . '): ' . $note);
            }
            $this->db->update('assets', (int) $row['asset_id'], $assetUpdate);
        }
        if ($finish) {
            $this->finishIfComplete($show);
        }
        $label = $row['asset_tag'] ? $row['item_name'] . ' ' . $row['asset_tag'] : $quantity . ' × ' . $row['item_name'];

        return $this->ok($label . ' returned' . ($damaged > 0 ? ' (marked damaged)' : '') . '.');
    }

    /** Mark the show returned once nothing is still out. */
    private function finishIfComplete(array $show): void
    {
        $out = (int) $this->db->value("SELECT COUNT(*) FROM checkouts WHERE show_id = ? AND status = 'out'", [(int) $show['id']]);
        $cases = (int) $this->db->value("SELECT COUNT(*) FROM show_containers WHERE show_id = ? AND status = 'out'", [(int) $show['id']]);
        $current = $this->db->value('SELECT status FROM shows WHERE id = ?', [(int) $show['id']]);
        if ($out === 0 && $cases === 0 && $current === 'out') {
            $this->db->update('shows', (int) $show['id'], ['status' => 'returned', 'updated_at' => now()]);
        }
    }

    /**
     * Totals for the show's warehouse tabs.
     */
    public function summary(array $show): array
    {
        $id = (int) $show['id'];
        $sum = fn (string $sql) => (int) $this->db->value($sql, [$id]);

        $containers = $this->db->all(
            'SELECT sc.*, c.name, c.barcode, c.kind FROM show_containers sc JOIN containers c ON c.id = sc.container_id
             WHERE sc.show_id = ? ORDER BY c.name',
            [$id],
        );
        foreach ($containers as &$container) {
            $container['contents'] = $this->db->all(
                'SELECT ch.*, i.name AS item_name, a.asset_tag FROM checkouts ch JOIN items i ON i.id = ch.item_id
                 LEFT JOIN assets a ON a.id = ch.asset_id WHERE ch.show_id = ? AND ch.container_id = ? ORDER BY i.name',
                [$id, (int) $container['container_id']],
            );
        }
        unset($container);

        $afterTransport = array_values(array_filter($containers, static fn ($c) => $c['return_after_transport'] && $c['status'] !== 'returned'));
        $staying = array_values(array_filter($containers, static fn ($c) => ! $c['return_after_transport'] && $c['status'] !== 'returned'));

        return [
            'required' => $sum('SELECT COALESCE(SUM(quantity), 0) FROM show_items WHERE show_id = ?'),
            'prepped' => $sum("SELECT COALESCE(SUM(quantity), 0) FROM checkouts WHERE show_id = ? AND status = 'prepped'"),
            'out' => $sum("SELECT COALESCE(SUM(quantity - returned_quantity), 0) FROM checkouts WHERE show_id = ? AND status = 'out'"),
            'returned' => $sum('SELECT COALESCE(SUM(returned_quantity), 0) FROM checkouts WHERE show_id = ?'),
            'damaged' => $sum('SELECT COALESCE(SUM(damaged_quantity), 0) FROM checkouts WHERE show_id = ?'),
            'sent' => $sum("SELECT COALESCE(SUM(quantity), 0) FROM checkouts WHERE show_id = ? AND status <> 'prepped'"),
            'containers' => $containers,
            'containers_after_transport' => $afterTransport,
            'containers_staying' => $staying,
        ];
    }

    /** Per-item progress: needed on the pull list vs packed/sent. */
    public function progress(array $show): array
    {
        $id = (int) $show['id'];
        $needed = $this->db->all(
            'SELECT si.item_id, i.name, i.tracking, SUM(si.quantity) AS needed FROM show_items si JOIN items i ON i.id = si.item_id
             WHERE si.show_id = ? GROUP BY si.item_id, i.name, i.tracking ORDER BY i.name',
            [$id],
        );
        $packed = [];
        foreach ($this->db->all('SELECT item_id, SUM(quantity) AS qty FROM checkouts WHERE show_id = ? GROUP BY item_id', [$id]) as $row) {
            $packed[(int) $row['item_id']] = (int) $row['qty'];
        }

        return array_map(static function ($row) use ($packed) {
            $row['packed'] = $packed[(int) $row['item_id']] ?? 0;
            $row['needed'] = (int) $row['needed'];
            $row['remaining'] = max(0, $row['needed'] - $row['packed']);

            return $row;
        }, $needed);
    }

    private function validContainer(array $show, ?int $containerId): ?int
    {
        if (! $containerId) {
            return null;
        }
        $exists = $this->db->value(
            "SELECT COUNT(*) FROM show_containers WHERE show_id = ? AND container_id = ? AND status <> 'returned'",
            [(int) $show['id'], $containerId],
        );
        if ($exists) {
            return $containerId;
        }
        $result = $this->addContainer($show, $containerId, false);

        return $result['ok'] ? $containerId : null;
    }

    private function packedMessage(string $label, ?int $containerId): string
    {
        if ($containerId === null) {
            return $label . ' packed.';
        }
        $name = $this->db->value('SELECT name FROM containers WHERE id = ?', [$containerId]);

        return $label . ' packed into ' . $name . '.';
    }

    private function pullListNote(array $show, int $itemId): string
    {
        $onList = (int) $this->db->value('SELECT COUNT(*) FROM show_items WHERE show_id = ? AND item_id = ?', [(int) $show['id'], $itemId]);

        return $onList ? '' : ' (Not on the pull list.)';
    }

    private function ok(string $message, array $extra = []): array
    {
        return ['ok' => true, 'message' => $message] + $extra;
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
