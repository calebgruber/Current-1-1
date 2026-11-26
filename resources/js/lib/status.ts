import type { Tone } from '@/types';

/** Visual tones for domain statuses – keep in sync with the PHP enums. */
export const jobStageTone: Record<string, Tone> = {
    inquiry: 'neutral',
    draft: 'neutral',
    quote: 'info',
    approved: 'accent',
    reserved: 'accent',
    prep: 'warning',
    ready: 'success',
    checked_out: 'info',
    returned: 'warning',
    closed: 'neutral',
    archived: 'neutral',
};

export const jobStages = [
    { value: 'inquiry', label: 'Inquiry' },
    { value: 'draft', label: 'Draft' },
    { value: 'quote', label: 'Quote' },
    { value: 'approved', label: 'Approved' },
    { value: 'reserved', label: 'Reserved' },
    { value: 'prep', label: 'Prep' },
    { value: 'ready', label: 'Ready' },
    { value: 'checked_out', label: 'Checked Out' },
    { value: 'returned', label: 'Returned' },
    { value: 'closed', label: 'Closed' },
    { value: 'archived', label: 'Archived' },
] as const;

export const assetStatusTone: Record<string, Tone> = {
    available: 'success',
    allocated: 'accent',
    checked_out: 'info',
    maintenance: 'warning',
    damaged: 'danger',
    lost: 'danger',
    retired: 'neutral',
};

export const maintenanceStatusTone: Record<string, Tone> = {
    open: 'warning',
    in_progress: 'info',
    awaiting_parts: 'warning',
    completed: 'success',
    written_off: 'neutral',
};

export const reservationStatusTone: Record<string, Tone> = {
    tentative: 'warning',
    confirmed: 'success',
    released: 'neutral',
};

export const severityTone: Record<string, Tone> = {
    error: 'danger',
    warning: 'warning',
    info: 'info',
};

export const humanize = (value: string | null | undefined) =>
    value ? value.replace(/[_-]+/g, ' ').replace(/^\w/, (c) => c.toUpperCase()) : '—';
