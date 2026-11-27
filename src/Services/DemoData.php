<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Example data for trying StageTrack out: a lighting and sound shop with kits,
 * shelf locations, road cases and a couple of shows. Safe to load more than once.
 */
final class DemoData
{
    public function __construct(private readonly Database $db)
    {
    }

    public function load(?int $userId): array
    {
        if ($this->db->value('SELECT COUNT(*) FROM items WHERE barcode = ?', ['ST-S4-26'])) {
            return ['ok' => false, 'message' => 'The demo data is already loaded.'];
        }

        return $this->db->transaction(function () use ($userId) {
            $now = now();
            $t = ['created_at' => $now, 'updated_at' => $now];

            $loc = [];
            foreach ([
                ['Lighting cage – Shelf A', 'LOC-A', 'Fixtures and lenses'],
                ['Lighting cage – Shelf B', 'LOC-B', 'Accessories, gel frames, safety cables'],
                ['Cable wall', 'LOC-CW', 'Hanging cable by length'],
                ['Sound closet', 'LOC-SND', 'Mics, DI boxes, speakers'],
                ['Consumables cabinet', 'LOC-CON', 'Tape, batteries, lamps'],
                ['Road case bay', 'LOC-RC', 'Empty road cases and tubs'],
            ] as $i => [$name, $code, $description]) {
                $loc[$code] = $this->db->insert('locations', ['name' => $name, 'code' => $code, 'description' => $description, 'sort' => $i] + $t);
            }

            $cat = [];
            foreach ([
                ['Conventional fixtures', 'lighting'], ['Moving & LED fixtures', 'lighting'], ['Lighting accessories', 'lighting'],
                ['Cable & power', 'lighting'], ['Microphones', 'sound'], ['Speakers & amps', 'sound'],
                ['Sound accessories', 'sound'], ['Consumables', 'general'], ['Tools & workboxes', 'general'],
            ] as $i => [$name, $department]) {
                $cat[$name] = $this->db->insert('categories', ['name' => $name, 'department' => $department, 'sort' => $i] + $t);
            }

            $item = function (string $name, string $category, string $tracking, string $barcode, int $qty, string $location, array $extra = []) use ($cat, $loc, $t): int {
                return $this->db->insert('items', [
                    'name' => $name,
                    'category_id' => $cat[$category],
                    'tracking' => $tracking,
                    'barcode' => $barcode,
                    'quantity' => $tracking === 'bulk' ? $qty : 0,
                    'location_id' => $loc[$location] ?? null,
                    'manufacturer' => $extra['manufacturer'] ?? null,
                    'model' => $extra['model'] ?? null,
                    'replacement_cost' => $extra['cost'] ?? null,
                    'active' => 1,
                ] + $t);
            };

            $s4 = $item('Source Four 26°', 'Conventional fixtures', 'serial', 'ST-S4-26', 0, 'LOC-A', ['manufacturer' => 'ETC', 'model' => 'Source Four', 'cost' => 450]);
            $s436 = $item('Source Four 36°', 'Conventional fixtures', 'serial', 'ST-S4-36', 0, 'LOC-A', ['manufacturer' => 'ETC', 'model' => 'Source Four', 'cost' => 450]);
            $par = $item('Colorsource PAR', 'Moving & LED fixtures', 'serial', 'ST-CSPAR', 0, 'LOC-A', ['manufacturer' => 'ETC', 'model' => 'ColorSource PAR', 'cost' => 1100]);
            $gel = $item('Gel frame 6.25"', 'Lighting accessories', 'bulk', 'ST-GF625', 60, 'LOC-B');
            $safety = $item('Safety cable', 'Lighting accessories', 'bulk', 'ST-SAFE', 120, 'LOC-B');
            $cclamp = $item('C-clamp', 'Lighting accessories', 'bulk', 'ST-CCLAMP', 80, 'LOC-B');
            $lamp = $item('HPL 575W lamp', 'Consumables', 'bulk', 'ST-HPL575', 24, 'LOC-CON');
            $tape = $item('Gaff tape – black', 'Consumables', 'bulk', 'ST-GAFF', 30, 'LOC-CON');
            $spike = $item('Spike tape – assorted', 'Consumables', 'bulk', 'ST-SPIKE', 40, 'LOC-CON');
            $aa = $item('AA batteries (pack of 4)', 'Consumables', 'bulk', 'ST-AA', 50, 'LOC-CON');
            $workbox = $item('Electrics workbox', 'Tools & workboxes', 'serial', 'ST-WBOX', 0, 'LOC-RC');
            $stage = $item('Stage pin cable 25\'', 'Cable & power', 'bulk', 'ST-SP25', 40, 'LOC-CW');
            $dmx = $item('5-pin DMX cable 25\'', 'Cable & power', 'bulk', 'ST-DMX25', 30, 'LOC-CW');
            $edison = $item('Edison extension 25\'', 'Cable & power', 'bulk', 'ST-ED25', 20, 'LOC-CW');
            $sm58 = $item('Shure SM58', 'Microphones', 'serial', 'ST-SM58', 0, 'LOC-SND', ['manufacturer' => 'Shure', 'model' => 'SM58', 'cost' => 99]);
            $wireless = $item('Wireless lav pack', 'Microphones', 'serial', 'ST-ULXD', 0, 'LOC-SND', ['manufacturer' => 'Shure', 'model' => 'ULXD1', 'cost' => 650]);
            $xlr = $item('XLR cable 25\'', 'Sound accessories', 'bulk', 'ST-XLR25', 50, 'LOC-SND');
            $stand = $item('Boom mic stand', 'Sound accessories', 'bulk', 'ST-STAND', 16, 'LOC-SND');
            $k10 = $item('QSC K10.2 speaker', 'Speakers & amps', 'serial', 'ST-K10', 0, 'LOC-SND', ['manufacturer' => 'QSC', 'model' => 'K10.2', 'cost' => 800]);

            // Kits: what automatically goes with each item.
            foreach ([
                [$s4, $gel, 1], [$s4, $safety, 1], [$s4, $cclamp, 1],
                [$s436, $gel, 1], [$s436, $safety, 1], [$s436, $cclamp, 1],
                [$par, $safety, 1], [$par, $cclamp, 1],
                [$workbox, $lamp, 2], [$workbox, $tape, 2], [$workbox, $spike, 1],
                [$wireless, $aa, 1],
                [$sm58, $xlr, 1], [$sm58, $stand, 1],
            ] as [$parent, $accessory, $qty]) {
                $this->db->insert('item_accessories', ['item_id' => $parent, 'accessory_id' => $accessory, 'quantity' => $qty] + $t);
            }

            $assets = function (int $itemId, string $prefix, int $count, string $location) use ($loc, $t): void {
                for ($i = 1; $i <= $count; $i++) {
                    $this->db->insert('assets', [
                        'item_id' => $itemId,
                        'asset_tag' => sprintf('%s-%03d', $prefix, $i),
                        'serial_number' => strtoupper(substr(md5($prefix . $i), 0, 10)),
                        'location_id' => $loc[$location],
                        'status' => 'available',
                    ] + $t);
                }
            };
            $assets($s4, 'S4-26', 12, 'LOC-A');
            $assets($s436, 'S4-36', 10, 'LOC-A');
            $assets($par, 'CSP', 8, 'LOC-A');
            $assets($workbox, 'WB', 3, 'LOC-RC');
            $assets($sm58, 'SM58', 10, 'LOC-SND');
            $assets($wireless, 'LAV', 12, 'LOC-SND');
            $assets($k10, 'K10', 4, 'LOC-SND');

            foreach ([
                ['Cable trunk 1', 'RC-001', 'trunk'], ['Cable trunk 2', 'RC-002', 'trunk'],
                ['Cable trunk 3', 'RC-003', 'trunk'], ['Cable trunk 4', 'RC-004', 'trunk'],
                ['S4 road case (6-way) A', 'RC-S4A', 'roadcase'], ['S4 road case (6-way) B', 'RC-S4B', 'roadcase'],
                ['Mic case', 'RC-MIC', 'roadcase'], ['Grey tub 1', 'TUB-01', 'tub'], ['Grey tub 2', 'TUB-02', 'tub'],
            ] as [$name, $barcode, $kind]) {
                $this->db->insert('containers', ['name' => $name, 'barcode' => $barcode, 'kind' => $kind, 'location_id' => $loc['LOC-RC'], 'active' => 1] + $t);
            }

            $director = $this->db->insert('contacts', ['name' => 'Jordan Ellis', 'organization' => 'Theatre Dept.', 'role' => 'Director', 'email' => 'jellis@example.edu', 'phone' => '555-0101'] + $t);
            $sm = $this->db->insert('contacts', ['name' => 'Priya Shah', 'organization' => 'Theatre Dept.', 'role' => 'Stage manager', 'email' => 'pshah@example.edu', 'phone' => '555-0102'] + $t);
            $ld = $this->db->insert('contacts', ['name' => 'Marcus Lee', 'organization' => 'Student', 'role' => 'Lighting designer', 'email' => 'mlee@example.edu', 'phone' => '555-0103'] + $t);
            $this->db->insert('contacts', ['name' => 'Ava Romero', 'organization' => 'Music Dept.', 'role' => 'Music director', 'email' => 'aromero@example.edu'] + $t);

            $base = strtotime('monday next week 09:00');
            $show = $this->db->insert('shows', [
                'reference' => 'S' . date('y') . '-001',
                'title' => 'Into the Woods',
                'status' => 'confirmed',
                'venue' => 'Main Stage Theatre',
                'department' => 'Theatre',
                'prep_at' => date('Y-m-d H:i:s', $base),
                'out_at' => date('Y-m-d H:i:s', $base + 2 * 86400),
                'opens_on' => date('Y-m-d', $base + 9 * 86400),
                'closes_on' => date('Y-m-d', $base + 18 * 86400),
                'return_at' => date('Y-m-d H:i:s', $base + 19 * 86400),
                'notes' => 'Load-in Wednesday 9am. Cable trunks 3 and 4 come straight back after transport.',
                'share_token' => bin2hex(random_bytes(16)),
                'created_by' => $userId,
            ] + $t);
            foreach ([[$director, 'Director'], [$sm, 'Stage manager'], [$ld, 'Lighting designer']] as [$contact, $role]) {
                $this->db->insert('show_contacts', ['show_id' => $show, 'contact_id' => $contact, 'role' => $role]);
            }
            $pull = new PullList($this->db);
            $pull->add($show, $s4, 8, 'Front of house');
            $pull->add($show, $par, 6, 'Overhead electrics');
            $pull->add($show, $stage, 20, 'Cable');
            $pull->add($show, $dmx, 10, 'Cable');
            $pull->add($show, $workbox, 1, 'Tools');
            $pull->add($show, $wireless, 8, 'Sound');

            $this->db->insert('shows', [
                'reference' => 'S' . date('y') . '-002',
                'title' => 'Spring Dance Concert',
                'status' => 'draft',
                'venue' => 'Black Box Studio',
                'department' => 'Dance',
                'out_at' => date('Y-m-d H:i:s', $base + 35 * 86400),
                'opens_on' => date('Y-m-d', $base + 38 * 86400),
                'closes_on' => date('Y-m-d', $base + 40 * 86400),
                'return_at' => date('Y-m-d H:i:s', $base + 41 * 86400),
                'share_token' => bin2hex(random_bytes(16)),
                'created_by' => $userId,
            ] + $t);

            $this->db->insert('messages', [
                'show_id' => $show, 'user_id' => null, 'author_name' => 'Priya Shah (Stage manager)', 'direction' => 'show',
                'body' => "Hi shop! Can we add two more wireless packs for the Witch's quick changes? Thanks!", 'created_at' => $now,
            ]);

            return ['ok' => true, 'message' => 'Demo data loaded: 19 items with kits, 59 serial units, 9 containers and 2 shows.'];
        });
    }
}
