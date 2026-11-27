<?php

namespace App\Support\Navigation;

use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the colour-coded module interface.
 *
 * Each module owns a colour, an icon, the route patterns that make it active and
 * a list of sub-tabs (its pages and primary actions). The layout renders the
 * modules as a top rail and the active module's sub-tabs underneath, so nothing
 * runs off the page. Every module and tab is permission-aware.
 */
class ModuleNavigation
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<int, array{key: string, label: string, icon: string, color: string, href: string, active: bool, tabs: array<int, array{label: string, icon: string, href: string, active: bool, action: bool}>}>
     */
    public function for(?Authorizable $user, Request $request): array
    {
        if ($user === null) {
            return [];
        }

        $modules = [];

        foreach ($this->definitions() as $module) {
            $tabs = [];

            foreach ($module['tabs'] as $tab) {
                if (! Route::has($tab['route']) || ! $this->allowed($user, $tab['can'] ?? null)) {
                    continue;
                }

                $tabs[] = [
                    'label' => $tab['label'],
                    'icon' => $tab['icon'],
                    'href' => route($tab['route']),
                    'active' => $request->routeIs(...(array) ($tab['active'] ?? $tab['route'])),
                    'action' => (bool) ($tab['action'] ?? false),
                ];
            }

            if ($tabs === []) {
                continue;
            }

            $modules[] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'icon' => $module['icon'],
                'color' => $module['color'],
                'href' => $tabs[0]['href'],
                'active' => $request->routeIs(...$module['active']),
                'tabs' => $tabs,
            ];
        }

        return $modules;
    }

    /** @param string|array<int, string>|null $abilities */
    private function allowed(Authorizable $user, string|array|null $abilities): bool
    {
        if ($abilities === null) {
            return true;
        }

        foreach ((array) $abilities as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }

        return false;
    }

    private function label(string $key, string $default): string
    {
        $value = $this->settings->get("ui.label_{$key}", $default);

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * Module definitions. Colours are hex so they work as CSS custom properties
     * without depending on Tailwind class scanning.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        $stations = [
            'picking' => ['Picking', 'clipboard-document-list'],
            'prep' => ['Prep', 'wrench'],
            'staging' => ['Staging', 'squares-2x2'],
            'checkout' => ['Check-out', 'truck'],
            'returns' => ['Returns', 'arrow-uturn-left'],
            'damage' => ['Damage', 'exclamation-triangle'],
        ];

        $warehouseTabs = [[
            'label' => 'Queue', 'icon' => 'queue-list', 'route' => 'warehouse.index',
            'can' => array_map(fn (string $slug) => "warehouse.{$slug}.view", array_keys($stations)),
        ]];
        foreach ($stations as $slug => [$label, $icon]) {
            $warehouseTabs[] = ['label' => $label, 'icon' => $icon, 'route' => "warehouse.{$slug}", 'can' => "warehouse.{$slug}.view"];
        }
        $warehouseTabs[] = ['label' => 'Display', 'icon' => 'tv', 'route' => 'warehouse.display', 'can' => 'warehouse.display.view'];

        return [
            [
                'key' => 'home', 'label' => 'Home', 'icon' => 'home', 'color' => '#4f46e5',
                'active' => ['dashboard', 'search.*'],
                'tabs' => [
                    ['label' => 'Dashboard', 'icon' => 'squares-2x2', 'route' => 'dashboard'],
                    ['label' => 'Search', 'icon' => 'magnifying-glass', 'route' => 'search.index', 'active' => 'search.*'],
                ],
            ],
            [
                'key' => 'jobs', 'label' => $this->label('jobs', 'Jobs'), 'icon' => 'briefcase', 'color' => '#7c3aed',
                'active' => ['jobs.*', 'reservations.*'],
                'tabs' => [
                    ['label' => 'All jobs', 'icon' => 'briefcase', 'route' => 'jobs.index', 'can' => 'jobs.jobs.view', 'active' => ['jobs.index', 'jobs.show', 'jobs.edit', 'jobs.pull-sheet', 'jobs.conflicts', 'jobs.validation']],
                    ['label' => 'New job', 'icon' => 'plus-circle', 'route' => 'jobs.create', 'can' => 'jobs.jobs.create', 'action' => true],
                    ['label' => $this->label('reservations', 'Reservations'), 'icon' => 'calendar-days', 'route' => 'reservations.index', 'can' => 'reservations.reservations.view', 'active' => ['reservations.index', 'reservations.show', 'reservations.edit']],
                    ['label' => 'New reservation', 'icon' => 'calendar', 'route' => 'reservations.create', 'can' => 'reservations.reservations.create', 'action' => true],
                ],
            ],
            [
                'key' => 'warehouse', 'label' => $this->label('warehouse', 'Warehouse'), 'icon' => 'qr-code', 'color' => '#d97706',
                'active' => ['warehouse.*'],
                'tabs' => $warehouseTabs,
            ],
            [
                'key' => 'inventory', 'label' => $this->label('inventory', 'Inventory'), 'icon' => 'archive-box', 'color' => '#0284c7',
                'active' => ['inventory.*', 'assets.*', 'availability.*'],
                'tabs' => [
                    ['label' => 'Products', 'icon' => 'archive-box', 'route' => 'inventory.products.index', 'can' => 'inventory.products.view', 'active' => ['inventory.products.index', 'inventory.products.show', 'inventory.products.edit']],
                    ['label' => 'New product', 'icon' => 'plus-circle', 'route' => 'inventory.products.create', 'can' => 'inventory.products.create', 'action' => true],
                    ['label' => 'Assets', 'icon' => 'cube', 'route' => 'assets.index', 'can' => 'assets.assets.view', 'active' => ['assets.index', 'assets.show', 'assets.edit']],
                    ['label' => 'New asset', 'icon' => 'plus-circle', 'route' => 'assets.create', 'can' => 'assets.assets.create', 'action' => true],
                    ['label' => 'Availability', 'icon' => 'calendar-days', 'route' => 'availability.index', 'can' => 'inventory.availability.view', 'active' => 'availability.*'],
                    ['label' => 'Categories', 'icon' => 'tag', 'route' => 'inventory.categories.index', 'can' => 'inventory.categories.view', 'active' => 'inventory.categories.*'],
                    ['label' => 'Manufacturers', 'icon' => 'building-office', 'route' => 'inventory.manufacturers.index', 'can' => 'inventory.categories.view', 'active' => 'inventory.manufacturers.*'],
                    ['label' => 'Locations', 'icon' => 'map-pin', 'route' => 'inventory.locations.index', 'can' => 'inventory.locations.view', 'active' => 'inventory.locations.*'],
                ],
            ],
            [
                'key' => 'maintenance', 'label' => $this->label('maintenance', 'Maintenance'), 'icon' => 'wrench-screwdriver', 'color' => '#e11d48',
                'active' => ['maintenance.*'],
                'tabs' => [
                    ['label' => 'Repairs', 'icon' => 'wrench-screwdriver', 'route' => 'maintenance.index', 'can' => 'maintenance.records.view', 'active' => ['maintenance.index', 'maintenance.show', 'maintenance.edit']],
                    ['label' => 'Log repair', 'icon' => 'plus-circle', 'route' => 'maintenance.create', 'can' => 'maintenance.records.create', 'action' => true],
                ],
            ],
            [
                'key' => 'contacts', 'label' => $this->label('contacts', 'Contacts'), 'icon' => 'users', 'color' => '#db2777',
                'active' => ['contacts.*'],
                'tabs' => [
                    ['label' => 'Directory', 'icon' => 'users', 'route' => 'contacts.index', 'can' => 'contacts.contacts.view', 'active' => ['contacts.index', 'contacts.show', 'contacts.edit']],
                    ['label' => 'New contact', 'icon' => 'user-plus', 'route' => 'contacts.create', 'can' => 'contacts.contacts.create', 'action' => true],
                ],
            ],
            [
                'key' => 'documents', 'label' => $this->label('documents', 'Documents'), 'icon' => 'document-text', 'color' => '#0891b2',
                'active' => ['documents.*'],
                'tabs' => [
                    ['label' => 'Generated', 'icon' => 'document-text', 'route' => 'documents.index', 'can' => 'documents.documents.view', 'active' => ['documents.index', 'documents.show']],
                    ['label' => 'Templates', 'icon' => 'document-duplicate', 'route' => 'documents.templates.index', 'can' => 'documents.templates.view', 'active' => ['documents.templates.index', 'documents.templates.edit']],
                    ['label' => 'New template', 'icon' => 'plus-circle', 'route' => 'documents.templates.create', 'can' => 'documents.templates.create', 'action' => true],
                ],
            ],
            [
                'key' => 'insights', 'label' => $this->label('reports', 'Reports'), 'icon' => 'chart-bar', 'color' => '#059669',
                'active' => ['reports.*', 'rules.*'],
                'tabs' => [
                    ['label' => 'Reports', 'icon' => 'chart-bar', 'route' => 'reports.index', 'can' => 'reporting.reports.view', 'active' => ['reports.index', 'reports.show', 'reports.edit', 'reports.schedules.*']],
                    ['label' => 'New report', 'icon' => 'plus-circle', 'route' => 'reports.create', 'can' => 'reporting.reports.create', 'action' => true],
                    ['label' => 'Rules', 'icon' => 'adjustments-horizontal', 'route' => 'rules.index', 'can' => 'rules.rules.view', 'active' => ['rules.index', 'rules.edit']],
                    ['label' => 'New rule', 'icon' => 'plus-circle', 'route' => 'rules.create', 'can' => 'rules.rules.create', 'action' => true],
                ],
            ],
            [
                'key' => 'settings', 'label' => 'Settings', 'icon' => 'cog-6-tooth', 'color' => '#475569',
                'active' => ['settings.*', 'admin.*'],
                'tabs' => [
                    ['label' => 'General', 'icon' => 'cog-6-tooth', 'route' => 'settings.general.index', 'can' => 'settings.general.view', 'active' => 'settings.general.*'],
                    ['label' => 'Custom fields', 'icon' => 'rectangle-group', 'route' => 'settings.custom-fields.index', 'can' => 'settings.custom_fields.view', 'active' => 'settings.custom-fields.*'],
                    ['label' => 'System', 'icon' => 'server-stack', 'route' => 'admin.system.index', 'can' => 'admin.system.view', 'active' => 'admin.*'],
                ],
            ],
            [
                'key' => 'account', 'label' => 'Account', 'icon' => 'user-circle', 'color' => '#9333ea',
                'active' => ['account.*'],
                'tabs' => [
                    ['label' => 'Profile', 'icon' => 'user-circle', 'route' => 'account.profile'],
                    ['label' => 'Password', 'icon' => 'key', 'route' => 'account.password'],
                    ['label' => 'Security', 'icon' => 'shield-check', 'route' => 'account.security', 'active' => ['account.security', 'account.security.*']],
                ],
            ],
        ];
    }
}
