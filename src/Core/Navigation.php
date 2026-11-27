<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The colour-coded modules shown across the top of every page, and the sub-tabs inside each.
 * Tabs whose ability the user lacks are hidden.
 */
final class Navigation
{
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'color' => '#4263eb', 'url' => '/',
                'tabs' => [
                    'overview' => ['Today', '/'],
                    'search' => ['Search', '/search'],
                ],
            ],
            'shows' => [
                'label' => 'Shows', 'icon' => 'masks-theater', 'color' => '#ae3ec9', 'url' => '/shows',
                'tabs' => [
                    'upcoming' => ['Upcoming', '/shows'],
                    'out' => ['Out now', '/shows?view=out'],
                    'past' => ['Past', '/shows?view=past'],
                    'all' => ['All shows', '/shows?view=all'],
                ],
                'action' => ['New show', '/shows/create', 'shows.manage'],
            ],
            'inventory' => [
                'label' => 'Inventory', 'icon' => 'bulb', 'color' => '#2fb344', 'url' => '/items',
                'tabs' => [
                    'items' => ['Items', '/items'],
                    'assets' => ['Serial units', '/assets'],
                    'categories' => ['Categories', '/categories'],
                    'locations' => ['Shop locations', '/locations'],
                    'containers' => ['Containers', '/containers'],
                ],
                'action' => ['New item', '/items/create', 'inventory.manage'],
            ],
            'warehouse' => [
                'label' => 'Warehouse', 'icon' => 'barcode', 'color' => '#f76707', 'url' => '/scan',
                'tabs' => [
                    'scan' => ['Scan & find', '/scan'],
                    'out' => ['Equipment out', '/warehouse/out'],
                    'repairs' => ['Repairs', '/warehouse/repairs'],
                    'station' => ['Fullscreen station', '/scan/station'],
                ],
            ],
            'calendar' => [
                'label' => 'Calendar', 'icon' => 'calendar-month', 'color' => '#0ca678', 'url' => '/calendar',
                'tabs' => [
                    'calendar' => ['Shop calendar', '/calendar'],
                ],
            ],
            'documents' => [
                'label' => 'Paperwork', 'icon' => 'file-text', 'color' => '#d6336c', 'url' => '/documents',
                'tabs' => [
                    'documents' => ['Documents', '/documents'],
                    'templates' => ['Templates', '/templates', 'templates.manage'],
                ],
            ],
            'contacts' => [
                'label' => 'Contacts', 'icon' => 'address-book', 'color' => '#1098ad', 'url' => '/contacts',
                'tabs' => [
                    'contacts' => ['People', '/contacts'],
                ],
                'action' => ['New contact', '/contacts/create', 'contacts.manage'],
            ],
            'admin' => [
                'label' => 'Admin', 'icon' => 'settings', 'color' => '#d63939', 'url' => '/admin/users', 'can' => 'admin',
                'tabs' => [
                    'users' => ['Users', '/admin/users'],
                    'settings' => ['Shop settings', '/admin/settings'],
                    'database' => ['Database', '/admin/database'],
                ],
            ],
        ];
    }

    /** Modules visible to the current user, with tabs filtered by ability. */
    public static function forUser(Auth $auth): array
    {
        $visible = [];
        foreach (self::modules() as $key => $module) {
            if (isset($module['can']) && ! $auth->can($module['can'])) {
                continue;
            }
            $module['tabs'] = array_filter($module['tabs'], static fn ($tab) => ! isset($tab[2]) || $auth->can($tab[2]));
            if (isset($module['action'][2]) && ! $auth->can($module['action'][2])) {
                unset($module['action']);
            }
            $visible[$key] = $module;
        }

        return $visible;
    }
}
