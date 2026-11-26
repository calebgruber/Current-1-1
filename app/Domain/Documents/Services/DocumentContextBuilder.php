<?php

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Jobs\Models\JobItem;
use App\Domain\Jobs\Models\ProductionJob;
use App\Domain\Maintenance\Models\MaintenanceRecord;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the plain-array context that document templates render against.
 * Every variable available here is listed in variables() for the designer's
 * variable picker; sample() provides realistic data for live previews.
 */
final class DocumentContextBuilder
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function forJob(ProductionJob $job, string $type, ?User $user = null): array
    {
        $job->loadMissing(['client', 'venue', 'owner', 'items.product.defaultLocation', 'items.assignedAssets.asset', 'customFieldValues.definition']);

        $items = $job->items->sortBy([['sort_order', 'asc'], ['id', 'asc']]);
        $roots = $items->whereNull('parent_item_id');
        $ordered = [];
        foreach ($roots as $root) {
            $ordered[] = $this->item($root);
            foreach ($items->where('parent_item_id', $root->id) as $child) {
                $ordered[] = $this->item($child);
            }
        }
        $rootIds = $roots->pluck('id')->all();
        foreach ($items->whereNotNull('parent_item_id') as $child) {
            if (! in_array($child->parent_item_id, $rootIds, true)) {
                $ordered[] = $this->item($child);
            }
        }

        $days = ($job->starts_at && $job->ends_at) ? max(1, (int) ceil($job->starts_at->diffInHours($job->ends_at) / 24)) : 1;

        return $this->base($type, $user) + [
            'job' => [
                'id' => $job->id,
                'number' => $job->number,
                'name' => $job->name,
                'stage' => $job->stage?->value,
                'stage_label' => $job->stage?->label(),
                'workflow_status' => $job->workflow_status,
                'description' => $job->description,
                'prep_starts_at' => $job->prep_starts_at?->toIso8601String(),
                'starts_at' => $job->starts_at?->toIso8601String(),
                'ends_at' => $job->ends_at?->toIso8601String(),
                'returns_at' => $job->returns_at?->toIso8601String(),
                'days' => $days,
                'client' => $job->client ? [
                    'name' => $job->client->name, 'email' => $job->client->email,
                    'phone' => $job->client->phone, 'address' => $job->client->address,
                ] : null,
                'venue' => $job->venue ? ['name' => $job->venue->name, 'address' => $job->venue->address] : null,
                'owner' => $job->owner ? ['name' => $job->owner->name, 'email' => $job->owner->email] : null,
                'custom' => $job->customFields(),
            ],
        ] + $this->itemsContext($ordered);
    }

    public function forMaintenance(MaintenanceRecord $record, string $type, ?User $user = null): array
    {
        $record->loadMissing(['product', 'asset.location', 'job', 'assignee', 'reporter', 'customFieldValues.definition']);

        return $this->base($type, $user) + [
            'maintenance' => [
                'reference' => $record->reference,
                'title' => $record->title,
                'type' => $record->type,
                'status' => $record->status?->label(),
                'priority' => $record->priority,
                'description' => $record->description,
                'resolution' => $record->resolution,
                'quantity' => $record->quantity,
                'cost' => (float) $record->cost,
                'due_at' => $record->due_at?->toIso8601String(),
                'started_at' => $record->started_at?->toIso8601String(),
                'completed_at' => $record->completed_at?->toIso8601String(),
                'reported_at' => $record->created_at?->toIso8601String(),
                'product' => $record->product?->name,
                'asset_number' => $record->asset?->asset_number,
                'serial_number' => $record->asset?->serial_number,
                'location' => $record->asset?->location?->name,
                'job_number' => $record->job?->number,
                'job_name' => $record->job?->name,
                'assigned_to' => $record->assignee?->name,
                'reported_by' => $record->reporter?->name,
                'custom' => $record->customFields(),
            ],
        ];
    }

    /** Realistic sample data for the live designer preview. */
    public function sample(string $type, ?User $user = null): array
    {
        $lines = [
            ['Lighting', 'ETC Source Four LED Series 3 Lustr X8', 'ETC-S4L3-LX8', 20, 5, 410.0, 4800.0, false, 'Electrics Shop › Rack A'],
            ['Lighting', 'Half-coupler clamp', 'CLAMP-HALF', 20, 5, 0.0, 25.0, true, 'Electrics Shop › Bin 4'],
            ['Lighting', 'Safety cable 3ft', 'SAFETY-3', 20, 5, 0.0, 12.0, true, 'Electrics Shop › Bin 5'],
            ['Lighting', 'PowerCON TRUE1 jumper 10ft', 'PC1-10', 20, 5, 2.5, 30.0, true, 'Electrics Shop › Cable wall'],
            ['Audio', 'Shure ULXD2/B58 handheld', 'ULXD2-B58', 8, 2, 35.0, 1350.0, false, 'Audio › Cage 2'],
            ['Audio', 'AA lithium battery', 'BATT-AA-LI', 16, 8, 1.5, 2.0, true, 'Audio › Consumables'],
            ['Audio', 'Yamaha Rio1608-D2 stagebox', 'RIO1608-D2', 1, 0, 180.0, 6200.0, false, 'Audio › Rack 1'],
        ];
        $items = [];
        foreach ($lines as [$section, $name, $sku, $req, $spare, $price, $replacement, $accessory, $location]) {
            $items[] = [
                'name' => $name, 'sku' => $sku, 'section' => $section, 'location' => $location,
                'required' => $req, 'spare' => $spare, 'pull' => $req + $spare,
                'picked' => 0, 'checked_out' => 0, 'returned' => 0, 'outstanding' => 0,
                'unit_price' => $price, 'line_total' => round($req * $price, 2),
                'replacement_cost' => $replacement, 'is_accessory' => $accessory,
                'notes' => null, 'assets' => '', 'weight_kg' => 0,
            ];
        }
        $start = now()->addDays(14)->setTime(19, 30);

        return $this->base($type, $user) + [
            'job' => [
                'id' => 0, 'number' => 'J-01042', 'name' => 'Spring Musical — Into the Woods',
                'stage' => 'approved', 'stage_label' => 'Approved', 'workflow_status' => null,
                'description' => 'Main stage production, 6 performances.',
                'prep_starts_at' => $start->copy()->subDays(5)->toIso8601String(),
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $start->copy()->addDays(10)->toIso8601String(),
                'returns_at' => $start->copy()->addDays(11)->toIso8601String(),
                'days' => 10,
                'client' => ['name' => 'Department of Theatre & Dance', 'email' => 'theatre@example.edu', 'phone' => '(555) 010-2000', 'address' => "123 College Ave\nSpringfield"],
                'venue' => ['name' => 'Mainstage Theatre', 'address' => 'Fine Arts Building'],
                'owner' => ['name' => $user?->name ?? 'Production Manager', 'email' => $user?->email ?? 'pm@example.edu'],
                'custom' => ['director' => 'A. Director', 'production_account' => 'THEA-4410'],
            ],
            'maintenance' => [
                'reference' => 'M-00321', 'title' => 'Fan noise on Lustr X8 #LX-0014', 'type' => 'repair',
                'status' => 'In progress', 'priority' => 'normal',
                'description' => 'Fan rattles above 60% intensity.', 'resolution' => null, 'quantity' => 1,
                'cost' => 85.0, 'due_at' => now()->addDays(3)->toIso8601String(), 'started_at' => now()->toIso8601String(),
                'completed_at' => null, 'reported_at' => now()->subDay()->toIso8601String(),
                'product' => 'ETC Source Four LED Series 3 Lustr X8', 'asset_number' => 'LX-0014', 'serial_number' => 'S4L3-22-11873',
                'location' => 'Electrics Shop', 'job_number' => 'J-01042', 'job_name' => 'Spring Musical — Into the Woods',
                'assigned_to' => 'Master Electrician', 'reported_by' => 'Student Crew', 'custom' => [],
            ],
        ] + $this->itemsContext($items);
    }

    /** Variable catalogue for the designer's insert-variable menu. */
    public function variables(): array
    {
        return [
            'Organization' => ['organization.name', 'organization.address', 'organization.logo_url'],
            'Document' => ['document.number', 'document.title', 'document.type_label', 'document.date', 'document.terms', 'user.name'],
            'Job' => ['job.number', 'job.name', 'job.stage_label', 'job.description', 'job.prep_starts_at', 'job.starts_at', 'job.ends_at', 'job.returns_at', 'job.days', 'job.client.name', 'job.client.email', 'job.client.phone', 'job.client.address', 'job.venue.name', 'job.owner.name', 'job.custom.<field_key>'],
            'Items (inside a loop)' => ['item.name', 'item.sku', 'item.section', 'item.location', 'item.required', 'item.spare', 'item.pull', 'item.picked', 'item.checked_out', 'item.returned', 'item.outstanding', 'item.unit_price', 'item.line_total', 'item.replacement_cost', 'item.is_accessory', 'item.assets', 'item.notes'],
            'Collections' => ['items', 'sections', 'section.name', 'section.items'],
            'Totals' => ['totals.required', 'totals.spare', 'totals.pull', 'totals.lines', 'totals.weight_kg', 'totals.subtotal', 'totals.tax', 'totals.tax_rate', 'totals.total', 'totals.replacement_value', 'totals.outstanding'],
            'Maintenance' => ['maintenance.reference', 'maintenance.title', 'maintenance.type', 'maintenance.status', 'maintenance.priority', 'maintenance.description', 'maintenance.resolution', 'maintenance.cost', 'maintenance.product', 'maintenance.asset_number', 'maintenance.serial_number', 'maintenance.assigned_to', 'maintenance.reported_by', 'maintenance.reported_at'],
            'Functions' => ['sum(items, "line_total")', 'count(items)', 'now()', 'round(x, 2)', 'min(a, b)', 'max(a, b)'],
            'Filters' => ['| currency', '| number(2)', '| date', '| date("datetime")', '| upper', '| default("—")', '| nl2br', '| yesno', '| truncate(40)', '| percent'],
        ];
    }

    private function base(string $type, ?User $user): array
    {
        $logo = $this->settings->get('general.logo_path');

        return [
            'organization' => [
                'name' => (string) $this->settings->get('general.organization_name'),
                'address' => $this->settings->get('general.address'),
                'logo_url' => $logo ? $this->logoDataUri((string) $logo) : null,
            ],
            'document' => [
                'number' => null,
                'title' => DocumentTemplate::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'type' => $type,
                'type_label' => DocumentTemplate::TYPES[$type] ?? $type,
                'date' => now()->toIso8601String(),
                'terms' => $this->settings->get('documents.terms'),
            ],
            'user' => ['name' => $user?->name, 'email' => $user?->email],
        ];
    }

    /** Embed the logo as a data URI so dompdf never needs remote/filesystem access. */
    private function logoDataUri(string $path): ?string
    {
        $disk = Storage::disk('public');
        if (str_contains($path, '..') || ! $disk->exists($path)) {
            return null;
        }
        $mime = $disk->mimeType($path) ?: 'image/png';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    private function item(JobItem $i): array
    {
        $required = (int) $i->required_quantity;
        $spare = (int) $i->spare_quantity;

        return [
            'name' => $i->product?->name,
            'sku' => $i->product?->sku,
            'section' => $i->section ?: 'General',
            'location' => $i->product?->defaultLocation?->path(),
            'required' => $required,
            'spare' => $spare,
            'pull' => $required + $spare,
            'picked' => (int) $i->picked_quantity,
            'checked_out' => (int) $i->checked_out_quantity,
            'returned' => (int) $i->returned_quantity,
            'outstanding' => max(0, (int) $i->checked_out_quantity - (int) $i->returned_quantity),
            'unit_price' => (float) $i->unit_price,
            'line_total' => round($required * (float) $i->unit_price, 2),
            'replacement_cost' => (float) $i->product?->replacement_cost,
            'is_accessory' => $i->parent_item_id !== null,
            'notes' => $i->notes,
            'assets' => $i->assignedAssets->map(fn ($a) => $a->asset?->asset_number)->filter()->implode(', '),
            'weight_kg' => (float) $i->product?->weight_kg,
        ];
    }

    /** @param list<array> $items */
    private function itemsContext(array $items): array
    {
        $sections = [];
        foreach ($items as $item) {
            $sections[$item['section']][] = $item;
        }
        $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
        $rate = (float) $this->settings->get('general.tax_rate', 0);
        $tax = round($subtotal * $rate / 100, 2);

        return [
            'items' => $items,
            'sections' => array_map(fn ($name, $list) => ['name' => $name, 'items' => $list], array_keys($sections), array_values($sections)),
            'totals' => [
                'lines' => count($items),
                'required' => array_sum(array_column($items, 'required')),
                'spare' => array_sum(array_column($items, 'spare')),
                'pull' => array_sum(array_column($items, 'pull')),
                'picked' => array_sum(array_column($items, 'picked')),
                'outstanding' => array_sum(array_column($items, 'outstanding')),
                'weight_kg' => round(array_sum(array_map(fn ($i) => $i['pull'] * $i['weight_kg'], $items)), 2),
                'replacement_value' => round(array_sum(array_map(fn ($i) => $i['pull'] * $i['replacement_cost'], $items)), 2),
                'subtotal' => $subtotal,
                'tax_rate' => $rate,
                'tax_label' => $this->settings->get('general.tax_label', 'Tax'),
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
            ],
        ];
    }
}
