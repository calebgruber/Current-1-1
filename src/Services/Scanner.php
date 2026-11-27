<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Resolves a scanned or typed barcode to the thing it belongs to.
 */
final class Scanner
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{type: string, id: int, label: string, url: string, record: array}|null
     */
    public function lookup(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $asset = $this->db->one(
            'SELECT a.*, i.name AS item_name FROM assets a JOIN items i ON i.id = a.item_id WHERE LOWER(a.asset_tag) = LOWER(?)',
            [$code],
        );
        if ($asset) {
            return $this->result('asset', $asset, $asset['item_name'] . ' · ' . $asset['asset_tag'], '/assets/' . $asset['id']);
        }

        $item = $this->db->one('SELECT * FROM items WHERE LOWER(barcode) = LOWER(?)', [$code]);
        if ($item) {
            return $this->result('item', $item, $item['name'], '/items/' . $item['id']);
        }

        $container = $this->db->one('SELECT * FROM containers WHERE LOWER(barcode) = LOWER(?)', [$code]);
        if ($container) {
            return $this->result('container', $container, $container['name'], '/containers/' . $container['id']);
        }

        $location = $this->db->one('SELECT * FROM locations WHERE LOWER(code) = LOWER(?)', [$code]);
        if ($location) {
            return $this->result('location', $location, $location['name'], '/locations/' . $location['id']);
        }

        $show = $this->db->one('SELECT * FROM shows WHERE LOWER(reference) = LOWER(?)', [$code]);
        if ($show) {
            return $this->result('show', $show, $show['reference'] . ' · ' . $show['title'], '/shows/' . $show['id']);
        }

        return null;
    }

    private function result(string $type, array $record, string $label, string $path): array
    {
        return ['type' => $type, 'id' => (int) $record['id'], 'label' => $label, 'url' => url($path), 'record' => $record];
    }
}
