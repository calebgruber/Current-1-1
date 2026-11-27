<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * The pre-built paperwork a theatre shop sends to and from a show, and the data
 * each template can use.
 */
final class Paperwork
{
    /** key => [name, description] — the HTML lives in database/templates/{key}.html */
    public const TEMPLATES = [
        'pull_sheet' => ['Pull sheet', 'What to pull from the shop, grouped by section, with shelf locations and tick boxes.'],
        'packing_list' => ['Packing list', 'What is packed in each road case, plus loose items.'],
        'checkout_receipt' => ['Checkout receipt', 'Everything that left the shop with the show, for the production to sign.'],
        'return_receipt' => ['Return receipt', 'What came back, what is still out and anything damaged.'],
        'outstanding_items' => ['Outstanding items notice', 'A friendly reminder listing equipment that has not come back.'],
        'damage_report' => ['Damage report', 'Damaged equipment from a show with notes, for repairs and follow-up.'],
        'loan_agreement' => ['Equipment loan agreement', 'Terms the production agrees to before equipment leaves the shop.'],
        'show_summary' => ['Show summary', 'One-page overview of dates, venue, team and equipment for the production.'],
        'contact_sheet' => ['Contact sheet', 'Production team contacts and the shop’s details.'],
        'case_labels' => ['Case labels', 'Large labels for each road case showing the show and contents.'],
    ];

    public function __construct(private readonly Database $db, private readonly Settings $settings)
    {
    }

    public static function defaultBody(string $key): string
    {
        $file = BASE_PATH . '/database/templates/' . $key . '.html';

        return is_file($file) ? (string) file_get_contents($file) : '<h1>{{ document.title }}</h1>';
    }

    /** Install any missing templates (safe to run again). */
    public function seedTemplates(): int
    {
        $added = 0;
        foreach (self::TEMPLATES as $key => [$name, $description]) {
            if ($this->db->value('SELECT COUNT(*) FROM document_templates WHERE template_key = ?', [$key])) {
                continue;
            }
            $this->db->insert('document_templates', [
                'template_key' => $key,
                'name' => $name,
                'description' => $description,
                'body' => self::defaultBody($key),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
        }

        return $added;
    }

    public function render(string $body, array $context): string
    {
        return (new TemplateEngine())->render($body, $context);
    }

    /** Render a template for a show and save it as a document. */
    public function generate(string $key, array $show, ?int $userId): int
    {
        $template = $this->db->one('SELECT * FROM document_templates WHERE template_key = ?', [$key]);
        if (! $template) {
            throw new \InvalidArgumentException('Unknown template.');
        }
        $title = $template['name'] . ' — ' . $show['title'];
        $context = $this->context($show, $userId, $title);

        return $this->db->insert('documents', [
            'show_id' => (int) $show['id'],
            'template_key' => $key,
            'title' => $title,
            'body' => $this->render($template['body'], $context),
            'shared' => 0,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Everything a template can use. */
    public function context(array $show, ?int $userId, string $title = 'Document'): array
    {
        $id = (int) $show['id'];
        $user = $userId ? $this->db->find('users', $userId) : null;

        $lines = [];
        $sections = [];
        $n = 0;
        foreach ((new PullList($this->db))->lines($id) as $row) {
            $line = [
                'n' => $row['parent_id'] === null ? ++$n : '',
                'item' => $row['item_name'],
                'category' => $row['category_name'] ?? '',
                'quantity' => (int) $row['quantity'],
                'notes' => $row['notes'] ?? '',
                'accessory' => $row['parent_id'] !== null,
                'location' => $row['location_name'] ?? '',
                'barcode' => $row['barcode'] ?? '',
                'tracking' => $row['tracking'],
            ];
            $lines[] = $line;
            $sectionName = $row['section'] ?: 'General';
            $sections[$sectionName] ??= ['name' => $sectionName, 'lines' => []];
            $sections[$sectionName]['lines'][] = $line;
        }

        $checkouts = $this->db->all(
            'SELECT ch.*, i.name AS item_name, a.asset_tag, a.serial_number, c.name AS container_name
             FROM checkouts ch JOIN items i ON i.id = ch.item_id
             LEFT JOIN assets a ON a.id = ch.asset_id
             LEFT JOIN containers c ON c.id = ch.container_id
             WHERE ch.show_id = ? ORDER BY c.name, i.name, a.asset_tag',
            [$id],
        );
        $packed = array_map(static fn ($r) => [
            'item' => $r['item_name'],
            'asset_tag' => $r['asset_tag'] ?? '',
            'serial_number' => $r['serial_number'] ?? '',
            'quantity' => (int) $r['quantity'],
            'container' => $r['container_name'] ?? '',
            'status' => $r['status'],
            'returned' => (int) $r['returned_quantity'],
            'damaged' => (int) $r['damaged_quantity'],
            'outstanding' => (int) $r['quantity'] - (int) $r['returned_quantity'],
            'notes' => $r['notes'] ?? '',
            'returned_at' => fmt_datetime($r['returned_at']),
        ], $checkouts);
        $sent = array_values(array_filter($packed, static fn ($r) => $r['status'] !== 'prepped'));

        $containers = [];
        foreach ((new Warehouse($this->db))->summary($show)['containers'] as $c) {
            $contents = array_map(static fn ($r) => [
                'item' => $r['item_name'],
                'asset_tag' => $r['asset_tag'] ?? '',
                'quantity' => (int) $r['quantity'],
            ], $c['contents']);
            $containers[] = [
                'name' => $c['name'],
                'barcode' => $c['barcode'] ?? '',
                'kind' => ucfirst($c['kind']),
                'return_after_transport' => (bool) $c['return_after_transport'],
                'comes_back' => $c['return_after_transport'] ? 'Straight after transport' : 'At strike, with its contents',
                'status' => $c['status'],
                'contents' => $contents,
                'count' => array_sum(array_column($contents, 'quantity')),
            ];
        }

        $contacts = array_map(static fn ($c) => [
            'name' => $c['name'],
            'role' => $c['show_role'] ?: ($c['role'] ?? ''),
            'organization' => $c['organization'] ?? '',
            'email' => $c['email'] ?? '',
            'phone' => $c['phone'] ?? '',
        ], $this->db->all(
            'SELECT c.*, sc.role AS show_role FROM show_contacts sc JOIN contacts c ON c.id = sc.contact_id WHERE sc.show_id = ? ORDER BY c.name',
            [$id],
        ));

        $settings = $this->settings->all();

        return [
            'document' => ['title' => $title],
            'shop' => [
                'name' => $settings['shop_name'],
                'department' => $settings['shop_department'],
                'institution' => $settings['institution'],
                'email' => $settings['shop_email'],
                'phone' => $settings['shop_phone'],
                'address' => $settings['shop_address'],
            ],
            'show' => [
                'reference' => $show['reference'],
                'title' => $show['title'],
                'status' => ucfirst($show['status']),
                'venue' => $show['venue'] ?? '',
                'department' => $show['department'] ?? '',
                'prep' => fmt_datetime($show['prep_at'] ?? null),
                'out' => fmt_datetime($show['out_at']),
                'opens' => fmt_date($show['opens_on'] ?? null),
                'closes' => fmt_date($show['closes_on'] ?? null),
                'return' => fmt_datetime($show['return_at']),
                'notes' => $show['notes'] ?? '',
            ],
            'contacts' => $contacts,
            'lines' => $lines,
            'sections' => array_values($sections),
            'packed' => $packed,
            'sent' => $sent,
            'loose' => array_values(array_filter($sent, static fn ($r) => $r['container'] === '')),
            'containers' => $containers,
            'containers_after_transport' => array_values(array_filter($containers, static fn ($c) => $c['return_after_transport'])),
            'returned' => array_values(array_filter($packed, static fn ($r) => $r['returned'] > 0)),
            'outstanding' => array_values(array_filter($sent, static fn ($r) => $r['outstanding'] > 0)),
            'damaged' => array_values(array_filter($packed, static fn ($r) => $r['damaged'] > 0)),
            'totals' => [
                'lines' => count(array_filter($lines, static fn ($l) => ! $l['accessory'])),
                'units' => array_sum(array_column($lines, 'quantity')),
                'sent' => array_sum(array_column($sent, 'quantity')),
                'returned' => array_sum(array_column($packed, 'returned')),
                'outstanding' => array_sum(array_column(array_filter($sent, static fn ($r) => $r['outstanding'] > 0), 'outstanding')),
                'damaged' => array_sum(array_column($packed, 'damaged')),
                'containers' => count($containers),
            ],
            'generated' => [
                'date' => date('D j M Y, g:ia'),
                'by' => $user['name'] ?? '',
            ],
            'footer' => $settings['receipt_footer'],
        ];
    }
}
