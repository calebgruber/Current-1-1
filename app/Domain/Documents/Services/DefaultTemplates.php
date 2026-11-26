<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;

/**
 * Starter visual templates for every paperwork type. Seeded on install and
 * available via "Reset to default" in the designer.
 */
final class DefaultTemplates
{
    /** @return array<string, array{name: string, entity_type: string, blocks: list<array>, watermark?: ?string}> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(DocumentTemplate::TYPES) as $type) {
            $out[$type] = self::for($type);
        }

        return $out;
    }

    public static function for(string $type): array
    {
        $label = DocumentTemplate::TYPES[$type] ?? ucfirst($type);
        $maintenance = in_array($type, ['maintenance_report', 'damage_report'], true);

        $blocks = $maintenance ? self::maintenance($type) : match ($type) {
            'quotation', 'project_quotation', 'draft_order', 'invoice' => self::commercial($type),
            'rental_agreement' => self::agreement(),
            'picking_list', 'pull_sheet', 'pack_sheet' => self::warehouse($type),
            'collection_note', 'delivery_note' => self::transport($type),
            'return_sheet' => self::returns(),
            default => self::commercial($type),
        };

        return [
            'name' => 'Default '.mb_strtolower($label),
            'entity_type' => $maintenance ? 'maintenance' : 'job',
            'blocks' => self::withIds($blocks),
            'watermark' => $type === 'draft_order' ? 'DRAFT' : null,
            'orientation' => in_array($type, ['pull_sheet', 'picking_list'], true) ? 'landscape' : 'portrait',
        ];
    }

    private static function header(string $title): array
    {
        return [
            'type' => 'columns',
            'props' => ['columns' => [
                ['width' => '50%', 'blocks' => [
                    ['type' => 'logo', 'props' => ['height' => 56]],
                    ['type' => 'text', 'props' => ['html' => '<div style="color:#52525b">{{ organization.address | nl2br }}</div>']],
                ]],
                ['width' => '50%', 'blocks' => [
                    ['type' => 'heading', 'props' => ['level' => 1, 'text' => $title], 'style' => ['align' => 'right']],
                    ['type' => 'text', 'props' => ['html' => '<div style="text-align:right;color:#52525b">{{ job.number }} · {{ document.date | date }}</div>']],
                ]],
            ]],
        ];
    }

    private static function jobFields(bool $client = true): array
    {
        $fields = [
            ['label' => 'Production', 'value' => 'job.name'],
            ['label' => 'Venue', 'value' => 'job.venue.name | default("—")'],
            ['label' => 'Prep', 'value' => 'job.prep_starts_at', 'format' => 'datetime'],
            ['label' => 'Starts', 'value' => 'job.starts_at', 'format' => 'datetime'],
            ['label' => 'Ends', 'value' => 'job.ends_at', 'format' => 'datetime'],
            ['label' => 'Return due', 'value' => 'job.returns_at', 'format' => 'datetime'],
        ];
        if ($client) {
            array_unshift($fields, ['label' => 'Client', 'value' => 'job.client.name | default("—")'], ['label' => 'Contact', 'value' => 'job.client.email | default("")']);
        }

        return ['type' => 'fields', 'props' => ['columns' => 2, 'fields' => $fields]];
    }

    private static function commercial(string $type): array
    {
        $title = DocumentTemplate::TYPES[$type];

        return [
            self::header($title),
            ['type' => 'divider'],
            self::jobFields(),
            ['type' => 'items_table', 'props' => [
                'group_by_section' => $type === 'project_quotation',
                'indent_accessories' => true,
                'columns' => [
                    ['label' => 'Item', 'value' => 'item.name'],
                    ['label' => 'Qty', 'value' => 'item.required', 'align' => 'right', 'width' => '10%'],
                    ['label' => 'Spare', 'value' => 'item.spare', 'align' => 'right', 'width' => '10%'],
                    ['label' => 'Unit', 'value' => 'item.unit_price', 'align' => 'right', 'format' => 'currency', 'width' => '14%'],
                    ['label' => 'Total', 'value' => 'item.line_total', 'align' => 'right', 'format' => 'currency', 'width' => '14%'],
                ],
            ]],
            ['type' => 'totals', 'props' => ['rows' => [
                ['label' => 'Subtotal', 'value' => 'totals.subtotal'],
                ['label' => 'Tax', 'value' => 'totals.tax'],
                ['label' => 'Total', 'value' => 'totals.total', 'emphasis' => true],
            ]]],
            ['type' => 'conditional', 'props' => ['condition' => 'document.terms', 'blocks' => [
                ['type' => 'heading', 'props' => ['level' => 3, 'text' => 'Terms']],
                ['type' => 'text', 'props' => ['html' => '<div style="font-size:8.5pt;color:#52525b">{{ document.terms | nl2br }}</div>']],
            ]]],
            ...($type === 'invoice' ? [] : [['type' => 'signature', 'props' => ['lines' => [['label' => 'Approved by (client)'], ['label' => 'For '.'{{ organization.name }}']], 'show_date' => true]]]),
        ];
    }

    private static function agreement(): array
    {
        return [
            self::header('Rental agreement'),
            self::jobFields(),
            ['type' => 'text', 'props' => ['html' => '<p>The hirer agrees to take responsibility for the equipment listed below from collection until its return, to use it only for the production named above, and to pay replacement costs for loss or damage beyond normal wear.</p>']],
            ['type' => 'items_table', 'props' => [
                'indent_accessories' => true,
                'columns' => [
                    ['label' => 'Item', 'value' => 'item.name'],
                    ['label' => 'Qty (incl. spares)', 'value' => 'item.pull', 'align' => 'right', 'width' => '16%'],
                    ['label' => 'Replacement', 'value' => 'item.replacement_cost', 'align' => 'right', 'format' => 'currency', 'width' => '16%'],
                ],
            ]],
            ['type' => 'totals', 'props' => ['rows' => [
                ['label' => 'Rental total', 'value' => 'totals.total'],
                ['label' => 'Replacement value', 'value' => 'totals.replacement_value', 'emphasis' => true],
            ]]],
            ['type' => 'signature', 'props' => ['lines' => [['label' => 'Hirer signature'], ['label' => 'Issued by']], 'show_date' => true]],
        ];
    }

    private static function warehouse(string $type): array
    {
        $title = DocumentTemplate::TYPES[$type];
        $columns = [
            ['label' => '✓', 'value' => "''", 'width' => '5%'],
            ['label' => 'Item', 'value' => 'item.name'],
            ['label' => 'Location', 'value' => 'item.location | default("—")', 'width' => '20%'],
            ['label' => 'Required', 'value' => 'item.required', 'align' => 'right', 'width' => '9%'],
            ['label' => 'Spare', 'value' => 'item.spare', 'align' => 'right', 'width' => '9%'],
            ['label' => 'Pull', 'value' => 'item.pull', 'align' => 'right', 'width' => '9%'],
        ];
        if ($type === 'pack_sheet') {
            $columns[] = ['label' => 'Assets', 'value' => 'item.assets | default("")', 'width' => '22%'];
        } else {
            $columns[] = ['label' => 'Picked', 'value' => 'item.picked', 'align' => 'right', 'width' => '9%'];
        }

        return [
            self::header($title),
            self::jobFields(false),
            ['type' => 'items_table', 'props' => ['group_by_section' => true, 'indent_accessories' => true, 'columns' => $columns]],
            ['type' => 'fields', 'props' => ['columns' => 4, 'fields' => [
                ['label' => 'Required units', 'value' => 'totals.required'],
                ['label' => 'Spare units', 'value' => 'totals.spare'],
                ['label' => 'Total pull', 'value' => 'totals.pull'],
                ['label' => 'Est. weight (kg)', 'value' => 'totals.weight_kg', 'format' => 'decimal'],
            ]]],
            ['type' => 'signature', 'props' => ['lines' => [['label' => 'Pulled by'], ['label' => 'Checked by']], 'show_date' => true]],
        ];
    }

    private static function transport(string $type): array
    {
        return [
            self::header(DocumentTemplate::TYPES[$type]),
            self::jobFields(),
            ['type' => 'items_table', 'props' => [
                'indent_accessories' => true,
                'columns' => [
                    ['label' => 'Item', 'value' => 'item.name'],
                    ['label' => 'Quantity', 'value' => 'item.pull', 'align' => 'right', 'width' => '12%'],
                    ['label' => 'Asset numbers', 'value' => 'item.assets | default("")', 'width' => '30%'],
                ],
            ]],
            ['type' => 'fields', 'props' => ['columns' => 2, 'fields' => [
                ['label' => 'Total units', 'value' => 'totals.pull'],
                ['label' => 'Est. weight (kg)', 'value' => 'totals.weight_kg', 'format' => 'decimal'],
            ]]],
            ['type' => 'signature', 'props' => ['lines' => [['label' => $type === 'delivery_note' ? 'Received by' : 'Collected by'], ['label' => 'Released by']], 'show_date' => true]],
        ];
    }

    private static function returns(): array
    {
        return [
            self::header('Return sheet'),
            self::jobFields(false),
            ['type' => 'items_table', 'props' => [
                'group_by_section' => true,
                'indent_accessories' => true,
                'columns' => [
                    ['label' => 'Item', 'value' => 'item.name'],
                    ['label' => 'Out', 'value' => 'item.checked_out', 'align' => 'right', 'width' => '9%'],
                    ['label' => 'Returned', 'value' => 'item.returned', 'align' => 'right', 'width' => '10%'],
                    ['label' => 'Outstanding', 'value' => 'item.outstanding', 'align' => 'right', 'width' => '11%'],
                    ['label' => 'Condition / notes', 'value' => "''", 'width' => '30%'],
                ],
            ]],
            ['type' => 'conditional', 'props' => ['condition' => 'totals.outstanding > 0', 'blocks' => [
                ['type' => 'text', 'props' => ['html' => '<p style="color:#b45309"><strong>{{ totals.outstanding }}</strong> unit(s) still outstanding.</p>']],
            ]]],
            ['type' => 'signature', 'props' => ['lines' => [['label' => 'Returned by'], ['label' => 'Checked in by']], 'show_date' => true]],
        ];
    }

    private static function maintenance(string $type): array
    {
        return [
            [
                'type' => 'columns',
                'props' => ['columns' => [
                    ['width' => '50%', 'blocks' => [['type' => 'logo', 'props' => ['height' => 56]]]],
                    ['width' => '50%', 'blocks' => [
                        ['type' => 'heading', 'props' => ['level' => 1, 'text' => DocumentTemplate::TYPES[$type]], 'style' => ['align' => 'right']],
                        ['type' => 'text', 'props' => ['html' => '<div style="text-align:right;color:#52525b">{{ maintenance.reference }} · {{ document.date | date }}</div>']],
                    ]],
                ]],
            ],
            ['type' => 'divider'],
            ['type' => 'heading', 'props' => ['level' => 2, 'text' => '{{ maintenance.title }}']],
            ['type' => 'fields', 'props' => ['columns' => 3, 'fields' => [
                ['label' => 'Product', 'value' => 'maintenance.product | default("—")'],
                ['label' => 'Asset', 'value' => 'maintenance.asset_number | default("—")'],
                ['label' => 'Serial', 'value' => 'maintenance.serial_number | default("—")'],
                ['label' => 'Status', 'value' => 'maintenance.status'],
                ['label' => 'Priority', 'value' => 'maintenance.priority', 'format' => 'upper'],
                ['label' => 'Quantity', 'value' => 'maintenance.quantity'],
                ['label' => 'Reported', 'value' => 'maintenance.reported_at', 'format' => 'date'],
                ['label' => 'Reported by', 'value' => 'maintenance.reported_by | default("—")'],
                ['label' => 'Job', 'value' => 'maintenance.job_number | default("—")'],
            ]]],
            ['type' => 'heading', 'props' => ['level' => 3, 'text' => $type === 'damage_report' ? 'Damage description' : 'Issue']],
            ['type' => 'text', 'props' => ['html' => '<p>{{ maintenance.description | default("—") | nl2br }}</p>']],
            ['type' => 'conditional', 'props' => ['condition' => 'maintenance.resolution', 'blocks' => [
                ['type' => 'heading', 'props' => ['level' => 3, 'text' => 'Resolution']],
                ['type' => 'text', 'props' => ['html' => '<p>{{ maintenance.resolution | nl2br }}</p>']],
            ]]],
            ['type' => 'totals', 'props' => ['rows' => [['label' => 'Cost', 'value' => 'maintenance.cost', 'emphasis' => true]]]],
            ['type' => 'signature', 'props' => ['lines' => [['label' => 'Technician'], ['label' => 'Supervisor']], 'show_date' => true]],
        ];
    }

    private static function withIds(array $blocks, string $prefix = 'b'): array
    {
        foreach ($blocks as $i => &$block) {
            $block['id'] ??= $prefix.($i + 1);
            if (isset($block['props']['blocks'])) {
                $block['props']['blocks'] = self::withIds($block['props']['blocks'], $block['id'].'-');
            }
            if (isset($block['props']['columns'][0]['blocks'])) {
                foreach ($block['props']['columns'] as $c => &$column) {
                    $column['blocks'] = self::withIds($column['blocks'], $block['id'].'c'.$c.'-');
                }
                unset($column);
            }
        }

        return $blocks;
    }
}
