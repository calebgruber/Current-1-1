<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Availability;

/** Items: the things the shop owns. Serial items have individually tagged units; bulk items have a count. */
final class ItemController extends Controller
{
    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const PHOTO_MAX_BYTES = 6 * 1024 * 1024;

    public function index(): Response
    {
        $q = $this->request->string('q');
        $category = $this->request->int('category');
        $tracking = $this->request->string('tracking');
        $where = ['i.active = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(i.name LIKE ? OR i.barcode LIKE ? OR i.manufacturer LIKE ? OR i.model LIKE ?
                OR EXISTS (SELECT 1 FROM assets a WHERE a.item_id = i.id AND a.asset_tag LIKE ?))';
            array_push($params, ...array_fill(0, 5, $this->db->like($q)));
        }
        if ($category) {
            $where[] = 'i.category_id = ?';
            $params[] = $category;
        }
        if (in_array($tracking, ['serial', 'bulk'], true)) {
            $where[] = 'i.tracking = ?';
            $params[] = $tracking;
        }

        $items = $this->db->all(
            'SELECT i.*, c.name AS category_name, l.name AS location_name,
                (SELECT COUNT(*) FROM assets a WHERE a.item_id = i.id AND a.status NOT IN (\'retired\', \'missing\', \'repair\')) AS units,
                (SELECT COALESCE(SUM(ch.quantity - ch.returned_quantity), 0) FROM checkouts ch WHERE ch.item_id = i.id AND ch.status = \'out\') AS out_now
             FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN locations l ON l.id = i.location_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort, c.name, i.name',
            $params,
        );

        return $this->view('items/index', [
            'title' => 'Items',
            'module' => 'inventory',
            'tab' => 'items',
            'items' => $items,
            'categories' => $this->db->all('SELECT * FROM categories ORDER BY sort, name'),
            'q' => $q,
            'category' => $category,
            'tracking' => $tracking,
        ]);
    }

    public function create(): Response
    {
        return $this->form([
            'name' => '', 'category_id' => $this->request->int('category') ?: null, 'tracking' => 'bulk', 'barcode' => '',
            'quantity' => 0, 'location_id' => null, 'manufacturer' => '', 'model' => '', 'description' => '',
            'notes' => '', 'replacement_cost' => '',
        ]);
    }

    public function store(): Response
    {
        $data = $this->validated();
        $id = $this->db->insert('items', $data + ['active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->success($data['name'] . ' added.' . ($data['tracking'] === 'serial' ? ' Now add its serial units below.' : ''));

        return $this->redirect('/items/' . $id);
    }

    public function show(string $id): Response
    {
        $item = $this->item($id);
        $availability = new Availability($this->db);
        $from = now();
        $to = date('Y-m-d H:i:s', strtotime('+120 days'));

        return $this->view('items/show', [
            'title' => $item['name'],
            'module' => 'inventory',
            'tab' => 'items',
            'item' => $item,
            'stock' => $availability->stock($item),
            'assets' => $this->db->all(
                'SELECT a.*, l.name AS location_name,
                    (SELECT s.id FROM checkouts ch JOIN shows s ON s.id = ch.show_id WHERE ch.asset_id = a.id AND ch.status IN (\'prepped\', \'out\') LIMIT 1) AS show_id,
                    (SELECT s.title FROM checkouts ch JOIN shows s ON s.id = ch.show_id WHERE ch.asset_id = a.id AND ch.status IN (\'prepped\', \'out\') LIMIT 1) AS show_title
                 FROM assets a LEFT JOIN locations l ON l.id = a.location_id WHERE a.item_id = ? ORDER BY a.asset_tag',
                [(int) $item['id']],
            ),
            'accessories' => $this->db->all(
                'SELECT ia.*, i.name, i.tracking FROM item_accessories ia JOIN items i ON i.id = ia.accessory_id WHERE ia.item_id = ? ORDER BY i.name',
                [(int) $item['id']],
            ),
            'partOf' => $this->db->all(
                'SELECT ia.quantity, i.id, i.name FROM item_accessories ia JOIN items i ON i.id = ia.item_id WHERE ia.accessory_id = ? ORDER BY i.name',
                [(int) $item['id']],
            ),
            'accessoryOptions' => $this->db->all(
                'SELECT id, name FROM items WHERE active = 1 AND id <> ? AND id NOT IN (SELECT accessory_id FROM item_accessories WHERE item_id = ?) ORDER BY name',
                [(int) $item['id'], (int) $item['id']],
            ),
            'bookings' => $availability->bookings((int) $item['id'], $from, $to),
            'outNow' => $this->db->all(
                "SELECT s.id, s.title, s.reference, SUM(ch.quantity - ch.returned_quantity) AS qty FROM checkouts ch JOIN shows s ON s.id = ch.show_id
                 WHERE ch.item_id = ? AND ch.status IN ('prepped', 'out') GROUP BY s.id, s.title, s.reference",
                [(int) $item['id']],
            ),
            'locations' => $this->locationOptions(),
        ]);
    }

    public function edit(string $id): Response
    {
        return $this->form($this->item($id));
    }

    public function update(string $id): Response
    {
        $item = $this->item($id);
        $data = $this->validated((int) $item['id']);
        if ($item['tracking'] === 'serial' && $data['tracking'] === 'bulk'
            && $this->db->value('SELECT COUNT(*) FROM assets WHERE item_id = ?', [(int) $item['id']])) {
            throw new ValidationException(['tracking' => 'Delete the serial units first before switching this item to bulk.']);
        }
        $this->db->update('items', (int) $item['id'], $data + ['updated_at' => now()]);
        $this->success('Saved.');

        return $this->redirect('/items/' . $item['id']);
    }

    public function destroy(string $id): Response
    {
        $item = $this->item($id);
        $used = (int) $this->db->value('SELECT COUNT(*) FROM show_items WHERE item_id = ?', [(int) $item['id']])
            + (int) $this->db->value('SELECT COUNT(*) FROM checkouts WHERE item_id = ?', [(int) $item['id']]);
        if ($used > 0) {
            // Keep history intact: hide it instead of deleting.
            $this->db->update('items', (int) $item['id'], ['active' => 0, 'barcode' => null, 'updated_at' => now()]);
            $this->success($item['name'] . ' has show history, so it was archived instead of deleted.');
        } else {
            $this->removePhotoFile($item);
            $this->db->delete('items', (int) $item['id']);
            $this->success($item['name'] . ' deleted.');
        }

        return $this->redirect('/items');
    }

    // ---- Photos ----

    public function photo(string $id): Response
    {
        $item = $this->item($id);
        $path = $this->photoPath($item['photo'] ?? null);
        if ($path === null) {
            throw new HttpException(404);
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mime = array_search($ext, self::PHOTO_TYPES, true) ?: 'application/octet-stream';

        return Response::file($path, $mime);
    }

    public function uploadPhoto(string $id): Response
    {
        $item = $this->item($id);
        $file = $this->request->file('photo');
        if ($file === null) {
            throw new ValidationException(['photo' => 'Choose a photo to upload.']);
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['photo' => in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'That photo is too large for the server. Try a smaller one.'
                : 'The upload failed. Please try again.']);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ! is_file($tmp) || filesize($tmp) > self::PHOTO_MAX_BYTES) {
            throw new ValidationException(['photo' => 'Photos must be smaller than 6 MB.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (! isset(self::PHOTO_TYPES[$mime]) || @getimagesize($tmp) === false) {
            throw new ValidationException(['photo' => 'That file is not a JPG, PNG, WebP or GIF image.']);
        }

        $dir = $this->uploadDir();
        $name = bin2hex(random_bytes(16)) . '.' . self::PHOTO_TYPES[$mime];
        $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dir . '/' . $name) : (PHP_SAPI === 'cli' && copy($tmp, $dir . '/' . $name));
        if (! $moved) {
            throw new ValidationException(['photo' => 'The photo could not be saved. Check that storage/uploads is writable.']);
        }
        $this->removePhotoFile($item);
        $this->db->update('items', (int) $item['id'], ['photo' => $name, 'updated_at' => now()]);
        $this->success('Photo saved.');

        return $this->redirect('/items/' . $item['id']);
    }

    public function deletePhoto(string $id): Response
    {
        $item = $this->item($id);
        $this->removePhotoFile($item);
        $this->db->update('items', (int) $item['id'], ['photo' => null, 'updated_at' => now()]);
        $this->success('Photo removed.');

        return $this->redirect('/items/' . $item['id']);
    }

    // ---- Kit accessories ----

    public function addAccessory(string $id): Response
    {
        $item = $this->item($id);
        $data = $this->validate(['accessory_id' => 'required|int', 'quantity' => 'required|int|min:1|max:999'], ['accessory_id' => 'Accessory']);
        if ($data['accessory_id'] === (int) $item['id'] || ! $this->db->find('items', $data['accessory_id'])) {
            throw new ValidationException(['accessory_id' => 'Pick a different item.']);
        }
        // Accessories are one level deep: an item that is itself a kit accessory of this item would loop.
        if ($this->db->value('SELECT COUNT(*) FROM item_accessories WHERE item_id = ? AND accessory_id = ?', [$data['accessory_id'], (int) $item['id']])) {
            throw new ValidationException(['accessory_id' => 'That item already has this one as its accessory.']);
        }
        $existing = $this->db->one('SELECT * FROM item_accessories WHERE item_id = ? AND accessory_id = ?', [(int) $item['id'], $data['accessory_id']]);
        if ($existing) {
            $this->db->update('item_accessories', (int) $existing['id'], ['quantity' => $data['quantity'], 'updated_at' => now()]);
        } else {
            $this->db->insert('item_accessories', [
                'item_id' => (int) $item['id'],
                'accessory_id' => $data['accessory_id'],
                'quantity' => $data['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->success('Kit updated. New pull lists will include it automatically.');

        return $this->redirect('/items/' . $item['id']);
    }

    public function removeAccessory(string $id, string $accessory): Response
    {
        $item = $this->item($id);
        $this->db->run('DELETE FROM item_accessories WHERE item_id = ? AND id = ?', [(int) $item['id'], (int) $accessory]);
        $this->success('Removed from the kit.');

        return $this->redirect('/items/' . $item['id']);
    }

    // ---- Helpers ----

    private function item(string $id): array
    {
        $item = $this->db->one(
            'SELECT i.*, c.name AS category_name, l.name AS location_name FROM items i
             LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN locations l ON l.id = i.location_id WHERE i.id = ?',
            [(int) $id],
        );
        if (! $item) {
            throw new HttpException(404);
        }

        return $item;
    }

    private function form(array $item): Response
    {
        $categories = [];
        foreach ($this->db->all('SELECT * FROM categories ORDER BY sort, name') as $c) {
            $categories[$c['id']] = $c['name'];
        }

        return $this->view('items/form', [
            'title' => isset($item['id']) ? 'Edit ' . $item['name'] : 'New item',
            'module' => 'inventory',
            'tab' => 'items',
            'item' => $item,
            'categories' => $categories,
            'locations' => $this->locationOptions(),
        ]);
    }

    private function locationOptions(): array
    {
        $options = [];
        foreach ($this->db->all('SELECT id, name, code FROM locations ORDER BY sort, name') as $l) {
            $options[$l['id']] = $l['name'] . ($l['code'] ? ' (' . $l['code'] . ')' : '');
        }

        return $options;
    }

    private function validated(?int $ignoreId = null): array
    {
        $data = $this->validate([
            'name' => 'required|max:190',
            'category_id' => 'int',
            'tracking' => 'required|in:serial,bulk',
            'barcode' => 'max:64',
            'quantity' => 'int|min:0|max:1000000',
            'location_id' => 'int',
            'manufacturer' => 'max:120',
            'model' => 'max:120',
            'description' => 'max:2000',
            'notes' => 'max:5000',
            'replacement_cost' => 'numeric|min:0',
        ], ['category_id' => 'Category', 'location_id' => 'Shop location', 'tracking' => 'Tracking']);

        if ($data['barcode'] !== null) {
            $this->assertBarcodeFree($data['barcode'], $ignoreId);
        }
        if ($data['category_id'] !== null && ! $this->db->find('categories', $data['category_id'])) {
            $data['category_id'] = null;
        }
        if ($data['location_id'] !== null && ! $this->db->find('locations', $data['location_id'])) {
            $data['location_id'] = null;
        }
        // Serial items count their units; the quantity field only applies to bulk.
        $data['quantity'] = $data['tracking'] === 'bulk' ? (int) $data['quantity'] : 0;

        return $data;
    }

    /** Barcodes must be unique across items, units, containers and locations so a scan always finds one thing. */
    private function assertBarcodeFree(string $barcode, ?int $ignoreItemId): void
    {
        $taken = (int) $this->db->value('SELECT COUNT(*) FROM items WHERE LOWER(barcode) = LOWER(?) AND id <> ?', [$barcode, $ignoreItemId ?? 0])
            + (int) $this->db->value('SELECT COUNT(*) FROM assets WHERE LOWER(asset_tag) = LOWER(?)', [$barcode])
            + (int) $this->db->value('SELECT COUNT(*) FROM containers WHERE LOWER(barcode) = LOWER(?)', [$barcode])
            + (int) $this->db->value('SELECT COUNT(*) FROM locations WHERE LOWER(code) = LOWER(?)', [$barcode]);
        if ($taken > 0) {
            throw new ValidationException(['barcode' => 'Another item, unit, container or location already uses that barcode.']);
        }
    }

    private function uploadDir(): string
    {
        $dir = BASE_PATH . '/storage/uploads';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function photoPath(?string $name): ?string
    {
        if ($name === null || ! preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $name)) {
            return null;
        }
        $path = $this->uploadDir() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    private function removePhotoFile(array $item): void
    {
        $path = $this->photoPath($item['photo'] ?? null);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
