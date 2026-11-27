<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Adds items to a show's pull list. Accessories configured on an item (gel frame,
 * safety cable, lamps…) are added automatically as child lines and kept in step
 * with the parent quantity.
 */
final class PullList
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return int the parent line id */
    public function add(int $showId, int $itemId, int $quantity, ?string $section = null, ?string $notes = null): int
    {
        $quantity = max(1, $quantity);

        return $this->db->transaction(function () use ($showId, $itemId, $quantity, $section, $notes) {
            // Adding the same item to the same section again just increases the quantity.
            $existing = $this->db->one(
                'SELECT * FROM show_items WHERE show_id = ? AND item_id = ? AND parent_id IS NULL AND COALESCE(section, \'\') = ?',
                [$showId, $itemId, (string) $section],
            );
            if ($existing) {
                $this->setQuantity((int) $existing['id'], (int) $existing['quantity'] + $quantity);

                return (int) $existing['id'];
            }

            $sort = (int) $this->db->value('SELECT COALESCE(MAX(sort), 0) + 1 FROM show_items WHERE show_id = ?', [$showId]);
            $lineId = $this->db->insert('show_items', [
                'show_id' => $showId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'parent_id' => null,
                'section' => $section ?: null,
                'notes' => $notes ?: null,
                'sort' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->accessories($itemId) as $accessory) {
                $this->db->insert('show_items', [
                    'show_id' => $showId,
                    'item_id' => (int) $accessory['accessory_id'],
                    'quantity' => $quantity * (int) $accessory['quantity'],
                    'parent_id' => $lineId,
                    'section' => $section ?: null,
                    'notes' => null,
                    'sort' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $lineId;
        });
    }

    /** Change a line's quantity; accessory lines follow their parent. */
    public function setQuantity(int $lineId, int $quantity): void
    {
        $line = $this->db->find('show_items', $lineId);
        if (! $line) {
            return;
        }
        $quantity = max(1, $quantity);
        $this->db->update('show_items', $lineId, ['quantity' => $quantity, 'updated_at' => now()]);

        if ($line['parent_id'] === null) {
            $perUnit = [];
            foreach ($this->accessories((int) $line['item_id']) as $a) {
                $perUnit[(int) $a['accessory_id']] = (int) $a['quantity'];
            }
            foreach ($this->db->all('SELECT * FROM show_items WHERE parent_id = ?', [$lineId]) as $child) {
                $per = $perUnit[(int) $child['item_id']] ?? null;
                if ($per !== null) {
                    $this->db->update('show_items', (int) $child['id'], ['quantity' => $quantity * $per, 'updated_at' => now()]);
                }
            }
        }
    }

    /** Pull list rows with item details, parents followed by their accessories. */
    public function lines(int $showId): array
    {
        $rows = $this->db->all(
            'SELECT si.*, i.name AS item_name, i.tracking, i.barcode, i.photo, c.name AS category_name,
                    l.name AS location_name
             FROM show_items si
             JOIN items i ON i.id = si.item_id
             LEFT JOIN categories c ON c.id = i.category_id
             LEFT JOIN locations l ON l.id = i.location_id
             WHERE si.show_id = ?
             ORDER BY COALESCE(si.section, \'\'), si.sort, si.parent_id IS NOT NULL, si.id',
            [$showId],
        );

        $parents = [];
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $parents[] = $row;
            } else {
                $children[(int) $row['parent_id']][] = $row;
            }
        }
        $ordered = [];
        foreach ($parents as $parent) {
            $ordered[] = $parent;
            foreach ($children[(int) $parent['id']] ?? [] as $child) {
                $ordered[] = $child;
            }
        }

        return $ordered;
    }

    private function accessories(int $itemId): array
    {
        return $this->db->all('SELECT accessory_id, quantity FROM item_accessories WHERE item_id = ?', [$itemId]);
    }
}
