<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Works out how many of an item the shop owns, how many are booked by shows over a
 * period, and how many are left.
 */
final class Availability
{
    /** Show statuses that no longer hold equipment. */
    public const INACTIVE_STATUSES = ['cancelled', 'closed', 'returned'];

    public function __construct(private readonly Database $db)
    {
    }

    /** Usable stock: bulk quantity, or serial units that are not retired, missing or in repair. */
    public function stock(array $item): int
    {
        if ($item['tracking'] === 'serial') {
            return (int) $this->db->value(
                "SELECT COUNT(*) FROM assets WHERE item_id = ? AND status NOT IN ('retired', 'missing', 'repair')",
                [(int) $item['id']],
            );
        }

        return max(0, (int) $item['quantity']);
    }

    /** Units of the item booked by shows overlapping [from, to). */
    public function booked(int $itemId, string $from, string $to, ?int $excludeShowId = null): int
    {
        $sql = 'SELECT COALESCE(SUM(si.quantity), 0) FROM show_items si JOIN shows s ON s.id = si.show_id
                WHERE si.item_id = ? AND s.out_at < ? AND s.return_at > ?
                AND s.status NOT IN (' . $this->placeholders(self::INACTIVE_STATUSES) . ')';
        $params = [$itemId, $to, $from, ...self::INACTIVE_STATUSES];
        if ($excludeShowId !== null) {
            $sql .= ' AND s.id <> ?';
            $params[] = $excludeShowId;
        }

        return (int) $this->db->value($sql, $params);
    }

    public function available(array $item, string $from, string $to, ?int $excludeShowId = null): int
    {
        return $this->stock($item) - $this->booked((int) $item['id'], $from, $to, $excludeShowId);
    }

    /**
     * For each item on a show's pull list: required, available to this show, and shortage.
     *
     * @return array<int, array{required:int, stock:int, other_shows:int, shortage:int}>
     */
    public function forShow(array $show): array
    {
        $rows = $this->db->all(
            'SELECT si.item_id, SUM(si.quantity) AS required FROM show_items si WHERE si.show_id = ? GROUP BY si.item_id',
            [(int) $show['id']],
        );
        $result = [];
        foreach ($rows as $row) {
            $item = $this->db->find('items', (int) $row['item_id']);
            if (! $item) {
                continue;
            }
            $stock = $this->stock($item);
            $others = $this->booked((int) $item['id'], $show['out_at'], $show['return_at'], (int) $show['id']);
            $required = (int) $row['required'];
            $result[(int) $item['id']] = [
                'required' => $required,
                'stock' => $stock,
                'other_shows' => $others,
                'shortage' => max(0, $required - ($stock - $others)),
            ];
        }

        return $result;
    }

    /**
     * Shows that have this item booked between two dates – used by the calendar.
     *
     * @return array<int, array>
     */
    public function bookings(int $itemId, string $from, string $to): array
    {
        return $this->db->all(
            'SELECT s.id, s.reference, s.title, s.status, s.venue, s.out_at, s.return_at, SUM(si.quantity) AS quantity
             FROM show_items si JOIN shows s ON s.id = si.show_id
             WHERE si.item_id = ? AND s.out_at < ? AND s.return_at > ? AND s.status <> ?
             GROUP BY s.id, s.reference, s.title, s.status, s.venue, s.out_at, s.return_at
             ORDER BY s.out_at',
            [$itemId, $to, $from, 'cancelled'],
        );
    }

    private function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }
}
