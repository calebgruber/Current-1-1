export interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
    two_factor_enabled: boolean;
    preferences: Record<string, unknown>;
}

export interface SharedProps {
    app: { name: string; version: string; organization: string; currency: string };
    auth: { user: AuthUser | null; permissions: string[] };
    flash: { success?: string | null; error?: string | null; warning?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface Option<V = string | number> {
    value: V;
    label: string;
}

export type Tone = 'neutral' | 'accent' | 'success' | 'warning' | 'danger' | 'info';

export interface AllocationSource {
    type: string;
    id: number | string | null;
    label: string;
    quantity: number;
    url: string | null;
    starts_at: string | null;
    ends_at: string | null;
    meta: Record<string, unknown>;
}

export interface AvailabilityBreakdown {
    owned: number;
    total: number;
    reserved: number;
    checked_out: number;
    tentative: number;
    maintenance: number;
    damaged: number;
    lost: number;
    retired: number;
    available: number;
    shortage: number;
    peak_at: string | null;
    sources: Record<string, AllocationSource[]>;
}

export interface ScanResult {
    level: 'success' | 'warning' | 'error';
    title: string;
    message: string;
    sound: 'success' | 'warning' | 'error';
    [key: string]: unknown;
}

export interface CustomFieldSchema {
    key: string;
    label: string;
    type: 'text' | 'long_text' | 'number' | 'currency' | 'date' | 'datetime' | 'checkbox' | 'dropdown' | 'multi_select' | 'file' | 'user' | 'asset' | 'url' | 'relation';
    group: string | null;
    help_text: string | null;
    options: (string | { value: string; label: string })[];
    validation: Record<string, unknown>;
    required: boolean;
    show_in_list: boolean;
}
