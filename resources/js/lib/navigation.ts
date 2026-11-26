import type { Component } from 'vue';
import {
    BarChart3, Boxes, Briefcase, CalendarRange, ClipboardCheck, Contact, FileText, Gauge, LayoutDashboard, ListChecks,
    MapPin, Monitor, Scale, ScanBarcode, Settings, Shield, SlidersHorizontal, Tag, Users, Wrench, FolderTree, ScrollText,
} from 'lucide-vue-next';

export interface NavItem {
    label: string;
    route: string;
    icon: Component;
    /** Any of these permissions grants access. Omit for always visible. */
    permissions?: string[];
    /** Route-name prefix used for active highlighting. */
    match?: string;
    shortcut?: string;
}

export interface NavSection {
    label: string;
    items: NavItem[];
}

/**
 * Sidebar navigation. Items are hidden when the user lacks permission or the
 * route is not registered (modules can be removed without breaking the UI).
 */
export const navigation: NavSection[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard, shortcut: 'g d' },
            { label: 'Availability', route: 'availability.index', icon: Gauge, permissions: ['inventory.availability.view'], match: 'availability', shortcut: 'g a' },
        ],
    },
    {
        label: 'Operations',
        items: [
            { label: 'Jobs', route: 'jobs.index', icon: Briefcase, permissions: ['jobs.jobs.view'], match: 'jobs', shortcut: 'g j' },
            { label: 'Reservations', route: 'reservations.index', icon: CalendarRange, permissions: ['reservations.reservations.view'], match: 'reservations', shortcut: 'g r' },
            { label: 'Contacts', route: 'contacts.index', icon: Contact, permissions: ['contacts.contacts.view'], match: 'contacts' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Products', route: 'inventory.products.index', icon: Boxes, permissions: ['inventory.products.view'], match: 'inventory.products', shortcut: 'g i' },
            { label: 'Assets', route: 'assets.index', icon: Tag, permissions: ['assets.assets.view'], match: 'assets', shortcut: 'g s' },
            { label: 'Categories', route: 'inventory.categories.index', icon: FolderTree, permissions: ['inventory.categories.view'], match: 'inventory.categories' },
            { label: 'Locations', route: 'inventory.locations.index', icon: MapPin, permissions: ['inventory.locations.view'], match: 'inventory.locations' },
            { label: 'Maintenance', route: 'maintenance.index', icon: Wrench, permissions: ['maintenance.records.view'], match: 'maintenance', shortcut: 'g m' },
        ],
    },
    {
        label: 'Warehouse',
        items: [
            { label: 'Warehouse', route: 'warehouse.index', icon: ScanBarcode, permissions: ['warehouse.picking.view', 'warehouse.prep.view', 'warehouse.staging.view', 'warehouse.checkout.view', 'warehouse.returns.view', 'warehouse.damage.view'], match: 'warehouse', shortcut: 'g w' },
            { label: 'Wall display', route: 'warehouse.display', icon: Monitor, permissions: ['warehouse.display.view'] },
        ],
    },
    {
        label: 'Paperwork & insight',
        items: [
            { label: 'Documents', route: 'documents.index', icon: FileText, permissions: ['documents.documents.view'], match: 'documents.documents' },
            { label: 'Templates', route: 'documents.templates.index', icon: ScrollText, permissions: ['documents.templates.view'], match: 'documents.templates' },
            { label: 'Reports', route: 'reports.index', icon: BarChart3, permissions: ['reporting.reports.view'], match: 'reports', shortcut: 'g p' },
            { label: 'Rules', route: 'rules.index', icon: ListChecks, permissions: ['rules.rules.view'], match: 'rules' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { label: 'Settings', route: 'settings.general', icon: Settings, permissions: ['settings.general.view'], match: 'settings.general' },
            { label: 'Custom fields', route: 'settings.custom-fields.index', icon: SlidersHorizontal, permissions: ['settings.custom_fields.view'], match: 'settings.custom-fields' },
            { label: 'Security policy', route: 'settings.security', icon: Shield, permissions: ['settings.security.view'], match: 'settings.security' },
            { label: 'Users', route: 'admin.users.index', icon: Users, permissions: ['admin.users.view'], match: 'admin.users' },
            { label: 'Roles', route: 'admin.roles.index', icon: Scale, permissions: ['admin.roles.view'], match: 'admin.roles' },
            { label: 'Audit log', route: 'admin.audit.index', icon: ClipboardCheck, permissions: ['admin.audit.view'], match: 'admin.audit' },
            { label: 'System', route: 'admin.system.index', icon: Gauge, permissions: ['admin.system.view', 'admin.backups.view', 'admin.logs.view', 'admin.updates.view'], match: 'admin.system' },
        ],
    },
];
