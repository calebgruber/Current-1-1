<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $now = now();
        $soon = date('Y-m-d H:i:s', strtotime('+30 days'));
        $upcoming = $this->db->all(
            "SELECT * FROM shows WHERE status IN ('draft', 'confirmed', 'prepping') AND return_at >= ? AND out_at <= ? ORDER BY out_at LIMIT 8",
            [$now, $soon],
        );
        $out = $this->db->all("SELECT * FROM shows WHERE status = 'out' ORDER BY return_at");
        $overdue = array_values(array_filter($out, static fn ($s) => $s['return_at'] < $now));

        return $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'module' => 'dashboard',
            'tab' => 'overview',
            'upcoming' => $upcoming,
            'out' => $out,
            'overdue' => $overdue,
            'stats' => [
                'upcoming' => (int) $this->db->value("SELECT COUNT(*) FROM shows WHERE status IN ('draft', 'confirmed', 'prepping') AND return_at >= ?", [$now]),
                'out' => count($out),
                'units_out' => (int) $this->db->value("SELECT COALESCE(SUM(quantity - returned_quantity), 0) FROM checkouts WHERE status = 'out'"),
                'repairs' => (int) $this->db->value("SELECT COUNT(*) FROM assets WHERE status = 'repair'"),
            ],
            'messages' => $this->db->all(
                "SELECT m.*, s.title AS show_title, s.reference FROM messages m JOIN shows s ON s.id = m.show_id
                 WHERE m.direction = 'show' ORDER BY m.created_at DESC, m.id DESC LIMIT 6",
            ),
            'activity' => $this->db->all(
                'SELECT a.*, u.name AS user_name, s.reference FROM activity a LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN shows s ON s.id = a.show_id ORDER BY a.created_at DESC, a.id DESC LIMIT 8',
            ),
        ]);
    }

    public function search(): Response
    {
        $q = $this->request->string('q');
        $results = [];
        if ($q !== '') {
            $like = Database::like($q);
            $results = [
                'Items' => array_map(static fn ($r) => [
                    'url' => '/items/' . $r['id'], 'icon' => 'bulb', 'title' => $r['name'],
                    'meta' => trim(($r['category_name'] ?? '') . ' · ' . ($r['barcode'] ?? ''), ' ·'),
                ], $this->db->all(
                    'SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON c.id = i.category_id
                     WHERE i.name LIKE ? OR i.barcode LIKE ? OR i.manufacturer LIKE ? OR i.model LIKE ? ORDER BY i.name LIMIT 25',
                    [$like, $like, $like, $like],
                )),
                'Serial units' => array_map(static fn ($r) => [
                    'url' => '/assets/' . $r['id'], 'icon' => 'tag', 'title' => $r['item_name'] . ' · ' . $r['asset_tag'],
                    'meta' => 'Serial ' . ($r['serial_number'] ?: '—'), 'status' => $r['status'],
                ], $this->db->all(
                    'SELECT a.*, i.name AS item_name FROM assets a JOIN items i ON i.id = a.item_id
                     WHERE a.asset_tag LIKE ? OR a.serial_number LIKE ? ORDER BY a.asset_tag LIMIT 25',
                    [$like, $like],
                )),
                'Shows' => array_map(static fn ($r) => [
                    'url' => '/shows/' . $r['id'], 'icon' => 'masks-theater', 'title' => $r['title'],
                    'meta' => $r['reference'] . ' · ' . fmt_date($r['out_at']), 'status' => $r['status'],
                ], $this->db->all(
                    'SELECT * FROM shows WHERE title LIKE ? OR reference LIKE ? OR venue LIKE ? ORDER BY out_at DESC LIMIT 25',
                    [$like, $like, $like],
                )),
                'Containers' => array_map(static fn ($r) => [
                    'url' => '/containers/' . $r['id'], 'icon' => 'box', 'title' => $r['name'], 'meta' => $r['barcode'],
                ], $this->db->all('SELECT * FROM containers WHERE name LIKE ? OR barcode LIKE ? ORDER BY name LIMIT 25', [$like, $like])),
                'Locations' => array_map(static fn ($r) => [
                    'url' => '/locations/' . $r['id'], 'icon' => 'map-pin', 'title' => $r['name'], 'meta' => $r['code'],
                ], $this->db->all('SELECT * FROM locations WHERE name LIKE ? OR code LIKE ? ORDER BY name LIMIT 25', [$like, $like])),
                'Contacts' => array_map(static fn ($r) => [
                    'url' => '/contacts/' . $r['id'], 'icon' => 'user', 'title' => $r['name'],
                    'meta' => trim(($r['role'] ?? '') . ' · ' . ($r['organization'] ?? ''), ' ·'),
                ], $this->db->all(
                    'SELECT * FROM contacts WHERE name LIKE ? OR email LIKE ? OR organization LIKE ? ORDER BY name LIMIT 25',
                    [$like, $like, $like],
                )),
            ];
            $results = array_filter($results);
        }

        return $this->view('dashboard/search', [
            'title' => $q !== '' ? 'Search: ' . $q : 'Search',
            'module' => 'dashboard',
            'tab' => 'search',
            'q' => $q,
            'results' => $results,
        ]);
    }
}
