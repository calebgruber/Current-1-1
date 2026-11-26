<?php

namespace App\Domain\Reporting\Services;

use InvalidArgumentException;

/**
 * Whitelist of everything the report builder can query. Report definitions
 * reference column KEYS only; SQL expressions never come from user input.
 *
 * Column: [label, sql, type, join?]
 *   type: string | text | number | currency | date | datetime | boolean | enum
 *   join: alias of a join that must be added when the column is used
 */
final class ReportSourceRegistry
{
    /** @return array<string, array> */
    public function all(): array
    {
        return [
            'products' => [
                'label' => 'Products',
                'table' => 'products',
                'permission' => 'inventory.products.view',
                'entity' => 'product',
                'soft_deletes' => true,
                'joins' => [
                    'category' => ['categories', 'base.category_id', 'id'],
                    'manufacturer' => ['manufacturers', 'base.manufacturer_id', 'id'],
                    'default_location' => ['locations', 'base.default_location_id', 'id'],
                ],
                'columns' => [
                    'id' => ['ID', 'base.id', 'number'],
                    'name' => ['Name', 'base.name', 'string'],
                    'sku' => ['SKU', 'base.sku', 'string'],
                    'barcode' => ['Barcode', 'base.barcode', 'string'],
                    'model' => ['Model', 'base.model', 'string'],
                    'tracking' => ['Tracking', 'base.tracking', 'enum'],
                    'category' => ['Category', 'category.name', 'string', 'category'],
                    'manufacturer' => ['Manufacturer', 'manufacturer.name', 'string', 'manufacturer'],
                    'default_location' => ['Default location', 'default_location.name', 'string', 'default_location'],
                    'replacement_cost' => ['Replacement cost', 'base.replacement_cost', 'currency'],
                    'rental_price' => ['Rental price', 'base.rental_price', 'currency'],
                    'weight_kg' => ['Weight (kg)', 'base.weight_kg', 'number'],
                    'is_active' => ['Active', 'base.is_active', 'boolean'],
                    'asset_count' => ['Serialized assets', '(select count(*) from assets a where a.product_id = base.id and a.deleted_at is null)', 'number'],
                    'bulk_quantity' => ['Bulk quantity', '(select coalesce(sum(s.quantity), 0) from stock_levels s where s.product_id = base.id)', 'number'],
                    'fleet_value' => ['Fleet replacement value', "(base.replacement_cost * (case when base.tracking = 'serialized' then (select count(*) from assets a where a.product_id = base.id and a.deleted_at is null) else (select coalesce(sum(s.quantity), 0) from stock_levels s where s.product_id = base.id) end))", 'currency'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
            'assets' => [
                'label' => 'Assets',
                'table' => 'assets',
                'permission' => 'assets.assets.view',
                'entity' => 'asset',
                'soft_deletes' => true,
                'joins' => [
                    'product' => ['products', 'base.product_id', 'id'],
                    'category' => ['categories', 'product.category_id', 'id', 'product'],
                    'location' => ['locations', 'base.location_id', 'id'],
                ],
                'columns' => [
                    'id' => ['ID', 'base.id', 'number'],
                    'asset_number' => ['Asset number', 'base.asset_number', 'string'],
                    'serial_number' => ['Serial number', 'base.serial_number', 'string'],
                    'barcode' => ['Barcode', 'base.barcode', 'string'],
                    'product' => ['Product', 'product.name', 'string', 'product'],
                    'category' => ['Category', 'category.name', 'string', 'category'],
                    'status' => ['Status', 'base.status', 'enum'],
                    'condition' => ['Condition', 'base.condition', 'enum'],
                    'location' => ['Location', 'location.name', 'string', 'location'],
                    'purchased_at' => ['Purchased', 'base.purchased_at', 'date'],
                    'purchase_cost' => ['Purchase cost', 'base.purchase_cost', 'currency'],
                    'replacement_cost' => ['Replacement cost', 'product.replacement_cost', 'currency', 'product'],
                    'warranty_expires_at' => ['Warranty expires', 'base.warranty_expires_at', 'date'],
                    'maintenance_count' => ['Maintenance records', '(select count(*) from maintenance_records m where m.asset_id = base.id)', 'number'],
                    'job_count' => ['Times allocated', '(select count(*) from job_item_assets j where j.asset_id = base.id)', 'number'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
            'jobs' => [
                'label' => 'Jobs',
                'table' => 'production_jobs',
                'permission' => 'jobs.jobs.view',
                'entity' => 'job',
                'soft_deletes' => true,
                'joins' => [
                    'client' => ['contacts', 'base.client_contact_id', 'id'],
                    'venue' => ['locations', 'base.venue_location_id', 'id'],
                    'owner' => ['users', 'base.owner_id', 'id'],
                ],
                'columns' => [
                    'id' => ['ID', 'base.id', 'number'],
                    'number' => ['Number', 'base.number', 'string'],
                    'name' => ['Name', 'base.name', 'string'],
                    'stage' => ['Stage', 'base.stage', 'enum'],
                    'workflow_status' => ['Workflow status', 'base.workflow_status', 'string'],
                    'client' => ['Client', 'client.name', 'string', 'client'],
                    'venue' => ['Venue', 'venue.name', 'string', 'venue'],
                    'owner' => ['Owner', 'owner.name', 'string', 'owner'],
                    'prep_starts_at' => ['Prep starts', 'base.prep_starts_at', 'datetime'],
                    'starts_at' => ['Starts', 'base.starts_at', 'datetime'],
                    'ends_at' => ['Ends', 'base.ends_at', 'datetime'],
                    'returns_at' => ['Returns', 'base.returns_at', 'datetime'],
                    'line_count' => ['Lines', '(select count(*) from job_items i where i.job_id = base.id)', 'number'],
                    'required_total' => ['Required units', '(select coalesce(sum(i.required_quantity), 0) from job_items i where i.job_id = base.id)', 'number'],
                    'spare_total' => ['Spare units', '(select coalesce(sum(i.spare_quantity), 0) from job_items i where i.job_id = base.id)', 'number'],
                    'pull_total' => ['Pull units', '(select coalesce(sum(i.required_quantity + i.spare_quantity), 0) from job_items i where i.job_id = base.id)', 'number'],
                    'value' => ['Rental value', '(select coalesce(sum(i.required_quantity * coalesce(i.unit_price, 0)), 0) from job_items i where i.job_id = base.id)', 'currency'],
                    'replacement_value' => ['Replacement value out', '(select coalesce(sum((i.required_quantity + i.spare_quantity) * coalesce(p.replacement_cost, 0)), 0) from job_items i join products p on p.id = i.product_id where i.job_id = base.id)', 'currency'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
            'job_items' => [
                'label' => 'Equipment allocations',
                'table' => 'job_items',
                'permission' => 'jobs.items.view',
                'entity' => null,
                'joins' => [
                    'job' => ['production_jobs', 'base.job_id', 'id'],
                    'product' => ['products', 'base.product_id', 'id'],
                    'category' => ['categories', 'product.category_id', 'id', 'product'],
                ],
                'columns' => [
                    'job_number' => ['Job number', 'job.number', 'string', 'job'],
                    'job' => ['Job', 'job.name', 'string', 'job'],
                    'job_stage' => ['Job stage', 'job.stage', 'enum', 'job'],
                    'job_starts_at' => ['Job starts', 'job.starts_at', 'datetime', 'job'],
                    'product' => ['Product', 'product.name', 'string', 'product'],
                    'category' => ['Category', 'category.name', 'string', 'category'],
                    'section' => ['Section', 'base.section', 'string'],
                    'is_accessory' => ['Accessory line', '(case when base.parent_item_id is null then 0 else 1 end)', 'boolean'],
                    'required_quantity' => ['Required', 'base.required_quantity', 'number'],
                    'spare_quantity' => ['Spare', 'base.spare_quantity', 'number'],
                    'pull_quantity' => ['Pull', '(base.required_quantity + base.spare_quantity)', 'number'],
                    'picked_quantity' => ['Picked', 'base.picked_quantity', 'number'],
                    'checked_out_quantity' => ['Checked out', 'base.checked_out_quantity', 'number'],
                    'returned_quantity' => ['Returned', 'base.returned_quantity', 'number'],
                    'outstanding_quantity' => ['Outstanding', '(base.checked_out_quantity - base.returned_quantity)', 'number'],
                    'unit_price' => ['Unit price', 'base.unit_price', 'currency'],
                    'line_value' => ['Line value', '(base.required_quantity * coalesce(base.unit_price, 0))', 'currency'],
                ],
            ],
            'reservations' => [
                'label' => 'Reservations',
                'table' => 'reservations',
                'permission' => 'reservations.reservations.view',
                'entity' => 'reservation',
                'joins' => [
                    'product' => ['products', 'base.product_id', 'id'],
                    'job' => ['production_jobs', 'base.job_id', 'id'],
                    'creator' => ['users', 'base.created_by', 'id'],
                ],
                'columns' => [
                    'id' => ['ID', 'base.id', 'number'],
                    'name' => ['Name', 'base.name', 'string'],
                    'product' => ['Product', 'product.name', 'string', 'product'],
                    'job_number' => ['Job number', 'job.number', 'string', 'job'],
                    'job' => ['Job', 'job.name', 'string', 'job'],
                    'status' => ['Status', 'base.status', 'enum'],
                    'quantity' => ['Required', 'base.quantity', 'number'],
                    'spare_quantity' => ['Spare', 'base.spare_quantity', 'number'],
                    'total_quantity' => ['Total held', '(base.quantity + base.spare_quantity)', 'number'],
                    'starts_at' => ['Starts', 'base.starts_at', 'datetime'],
                    'ends_at' => ['Ends', 'base.ends_at', 'datetime'],
                    'created_by' => ['Created by', 'creator.name', 'string', 'creator'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance records',
                'table' => 'maintenance_records',
                'permission' => 'maintenance.records.view',
                'entity' => 'maintenance',
                'joins' => [
                    'product' => ['products', 'base.product_id', 'id'],
                    'asset' => ['assets', 'base.asset_id', 'id'],
                    'assignee' => ['users', 'base.assigned_to', 'id'],
                    'reporter' => ['users', 'base.reported_by', 'id'],
                    'job' => ['production_jobs', 'base.job_id', 'id'],
                ],
                'columns' => [
                    'reference' => ['Reference', 'base.reference', 'string'],
                    'title' => ['Title', 'base.title', 'string'],
                    'type' => ['Type', 'base.type', 'enum'],
                    'status' => ['Status', 'base.status', 'enum'],
                    'priority' => ['Priority', 'base.priority', 'enum'],
                    'product' => ['Product', 'product.name', 'string', 'product'],
                    'asset_number' => ['Asset number', 'asset.asset_number', 'string', 'asset'],
                    'quantity' => ['Quantity', 'base.quantity', 'number'],
                    'cost' => ['Cost', 'base.cost', 'currency'],
                    'job_number' => ['Job number', 'job.number', 'string', 'job'],
                    'assigned_to' => ['Assigned to', 'assignee.name', 'string', 'assignee'],
                    'reported_by' => ['Reported by', 'reporter.name', 'string', 'reporter'],
                    'due_at' => ['Due', 'base.due_at', 'datetime'],
                    'started_at' => ['Started', 'base.started_at', 'datetime'],
                    'completed_at' => ['Completed', 'base.completed_at', 'datetime'],
                    'created_at' => ['Reported', 'base.created_at', 'datetime'],
                ],
            ],
            'locations' => [
                'label' => 'Locations',
                'table' => 'locations',
                'permission' => 'inventory.locations.view',
                'entity' => 'location',
                'joins' => [
                    'parent' => ['locations', 'base.parent_id', 'id'],
                ],
                'columns' => [
                    'name' => ['Name', 'base.name', 'string'],
                    'code' => ['Code', 'base.code', 'string'],
                    'type' => ['Type', 'base.type', 'enum'],
                    'parent' => ['Parent', 'parent.name', 'string', 'parent'],
                    'is_active' => ['Active', 'base.is_active', 'boolean'],
                    'asset_count' => ['Assets here', '(select count(*) from assets a where a.location_id = base.id and a.deleted_at is null)', 'number'],
                    'bulk_quantity' => ['Bulk units here', '(select coalesce(sum(s.quantity), 0) from stock_levels s where s.location_id = base.id)', 'number'],
                ],
            ],
            'users' => [
                'label' => 'Users',
                'table' => 'users',
                'permission' => 'admin.users.view',
                'entity' => null,
                'soft_deletes' => true,
                'joins' => [],
                'columns' => [
                    'name' => ['Name', 'base.name', 'string'],
                    'username' => ['Username', 'base.username', 'string'],
                    'email' => ['Email', 'base.email', 'string'],
                    'is_active' => ['Active', 'base.is_active', 'boolean'],
                    'is_super_admin' => ['Super admin', 'base.is_super_admin', 'boolean'],
                    'two_factor' => ['Two-factor enabled', '(case when base.two_factor_confirmed_at is null then 0 else 1 end)', 'boolean'],
                    'passkey_count' => ['Passkeys', '(select count(*) from passkeys pk where pk.user_id = base.id)', 'number'],
                    'roles' => ['Role count', '(select count(*) from role_user ru where ru.user_id = base.id)', 'number'],
                    'last_login_at' => ['Last login', 'base.last_login_at', 'datetime'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
            'documents' => [
                'label' => 'Generated documents',
                'table' => 'generated_documents',
                'permission' => 'documents.documents.view',
                'entity' => 'document',
                'joins' => [
                    'template' => ['document_templates', 'base.template_id', 'id'],
                    'creator' => ['users', 'base.created_by', 'id'],
                ],
                'columns' => [
                    'number' => ['Number', 'base.number', 'string'],
                    'title' => ['Title', 'base.title', 'string'],
                    'type' => ['Type', 'base.type', 'enum'],
                    'template' => ['Template', 'template.name', 'string', 'template'],
                    'created_by' => ['Created by', 'creator.name', 'string', 'creator'],
                    'created_at' => ['Created', 'base.created_at', 'datetime'],
                ],
            ],
        ];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): array
    {
        $sources = $this->all();
        if (! isset($sources[$key])) {
            throw new InvalidArgumentException("Unknown report source [{$key}].");
        }

        return $sources[$key] + ['key' => $key, 'soft_deletes' => false];
    }

    /** Front-end catalogue: sources with column metadata (no SQL). */
    public function catalogue(?callable $can = null, array $customFields = []): array
    {
        $out = [];
        foreach ($this->all() as $key => $source) {
            if ($can && ! $can($source['permission'])) {
                continue;
            }
            $columns = [];
            foreach ($source['columns'] as $ck => $c) {
                $columns[] = ['key' => $ck, 'label' => $c[0], 'type' => $c[2]];
            }
            foreach ($customFields[$source['entity'] ?? ''] ?? [] as $cf) {
                $columns[] = ['key' => 'cf:'.$cf['key'], 'label' => $cf['label'], 'type' => $cf['report_type'], 'custom' => true];
            }
            $out[] = ['key' => $key, 'label' => $source['label'], 'columns' => $columns];
        }

        return $out;
    }
}
