<?php

/*
|--------------------------------------------------------------------------
| Permission Catalog
|--------------------------------------------------------------------------
|
| The catalog enumerates every capability the application can check. It is
| synchronised into the `permissions` table (php artisan stagetrack:sync-permissions
| or Administration → Roles → "Sync catalog"). Roles are NOT defined here:
| administrators create unlimited roles in the database and attach any
| combination of these permissions. Keys follow "{module}.{resource}.{action}".
|
| Standard actions: view, create, edit, delete, export, approve, admin.
|
*/

$crud = ['view', 'create', 'edit', 'delete', 'export'];

return [
    'inventory' => [
        'label' => 'Inventory',
        'resources' => [
            'products' => ['label' => 'Products', 'actions' => [...$crud, 'admin']],
            'categories' => ['label' => 'Categories & manufacturers', 'actions' => ['view', 'create', 'edit', 'delete']],
            'locations' => ['label' => 'Locations', 'actions' => ['view', 'create', 'edit', 'delete']],
            'stock' => ['label' => 'Stock adjustments', 'actions' => ['view', 'edit', 'approve']],
            'availability' => ['label' => 'Availability Explorer', 'actions' => ['view', 'export']],
        ],
    ],
    'assets' => [
        'label' => 'Assets',
        'resources' => [
            'assets' => ['label' => 'Serialized assets', 'actions' => [...$crud, 'admin']],
            'status' => ['label' => 'Asset status changes', 'actions' => ['edit', 'approve']],
            'labels' => ['label' => 'Barcode & QR labels', 'actions' => ['view', 'export']],
        ],
    ],
    'contacts' => [
        'label' => 'Contacts',
        'resources' => [
            'contacts' => ['label' => 'Contacts & organizations', 'actions' => $crud],
        ],
    ],
    'jobs' => [
        'label' => 'Jobs',
        'resources' => [
            'jobs' => ['label' => 'Jobs', 'actions' => [...$crud, 'approve', 'admin']],
            'items' => ['label' => 'Equipment allocations', 'actions' => ['view', 'create', 'edit', 'delete']],
            'stages' => ['label' => 'Lifecycle transitions', 'actions' => ['edit', 'approve']],
            'files' => ['label' => 'Files & notes', 'actions' => ['view', 'create', 'delete']],
        ],
    ],
    'reservations' => [
        'label' => 'Reservations',
        'resources' => [
            'reservations' => ['label' => 'Reservations & holds', 'actions' => [...$crud, 'approve']],
            'overbooking' => ['label' => 'Override overbooking conflicts', 'actions' => ['approve']],
        ],
    ],
    'warehouse' => [
        'label' => 'Warehouse',
        'resources' => [
            'picking' => ['label' => 'Picking', 'actions' => ['view', 'edit']],
            'prep' => ['label' => 'Prep', 'actions' => ['view', 'edit']],
            'staging' => ['label' => 'Staging', 'actions' => ['view', 'edit']],
            'checkout' => ['label' => 'Check out', 'actions' => ['view', 'edit']],
            'returns' => ['label' => 'Returns', 'actions' => ['view', 'edit']],
            'damage' => ['label' => 'Damage processing', 'actions' => ['view', 'edit', 'approve']],
            'display' => ['label' => 'Fullscreen display', 'actions' => ['view']],
        ],
    ],
    'maintenance' => [
        'label' => 'Maintenance',
        'resources' => [
            'records' => ['label' => 'Maintenance records', 'actions' => [...$crud, 'approve']],
        ],
    ],
    'documents' => [
        'label' => 'Documents',
        'resources' => [
            'documents' => ['label' => 'Generated documents', 'actions' => ['view', 'create', 'delete', 'export']],
            'templates' => ['label' => 'Template designer', 'actions' => ['view', 'create', 'edit', 'delete', 'admin']],
        ],
    ],
    'reporting' => [
        'label' => 'Reporting',
        'resources' => [
            'reports' => ['label' => 'Reports', 'actions' => [...$crud, 'admin']],
            'schedules' => ['label' => 'Scheduled delivery', 'actions' => ['view', 'create', 'edit', 'delete']],
            'dashboard' => ['label' => 'Dashboard widgets', 'actions' => ['view']],
        ],
    ],
    'rules' => [
        'label' => 'Rules',
        'resources' => [
            'rules' => ['label' => 'Dependency & validation rules', 'actions' => ['view', 'create', 'edit', 'delete']],
        ],
    ],
    'settings' => [
        'label' => 'Settings',
        'resources' => [
            'general' => ['label' => 'Organization settings', 'actions' => ['view', 'edit']],
            'custom_fields' => ['label' => 'Custom fields', 'actions' => ['view', 'create', 'edit', 'delete']],
            'security' => ['label' => 'Security policies', 'actions' => ['view', 'edit']],
        ],
    ],
    'admin' => [
        'label' => 'Administration',
        'resources' => [
            'users' => ['label' => 'Users', 'actions' => [...$crud, 'admin']],
            'roles' => ['label' => 'Roles & permissions', 'actions' => ['view', 'create', 'edit', 'delete']],
            'audit' => ['label' => 'Audit log', 'actions' => ['view', 'export']],
            'system' => ['label' => 'System maintenance (migrations, cache, health, storage)', 'actions' => ['view', 'admin']],
            'backups' => ['label' => 'Backups & restore', 'actions' => ['view', 'create', 'delete', 'admin']],
            'logs' => ['label' => 'Application logs', 'actions' => ['view']],
            'updates' => ['label' => 'Application updates', 'actions' => ['view', 'admin']],
        ],
    ],
];
