import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

export function useFormat() {
    const page = usePage<SharedProps>();
    const currencyCode = () => page.props.app?.currency ?? 'USD';

    const currency = (value: number | string | null | undefined) =>
        value === null || value === undefined || value === ''
            ? '—'
            : new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode() }).format(Number(value));

    const number = (value: number | string | null | undefined, digits = 0) =>
        value === null || value === undefined || value === '' ? '—' : new Intl.NumberFormat(undefined, { maximumFractionDigits: digits }).format(Number(value));

    const date = (value: string | null | undefined) =>
        value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : '—';

    const datetime = (value: string | null | undefined) =>
        value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';

    const relative = (value: string | null | undefined) => {
        if (!value) return '—';
        const diff = (new Date(value).getTime() - Date.now()) / 1000;
        const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
        const units: [Intl.RelativeTimeFormatUnit, number][] = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
        for (const [unit, secs] of units) {
            if (Math.abs(diff) >= secs) return rtf.format(Math.round(diff / secs), unit);
        }
        return rtf.format(Math.round(diff), 'second');
    };

    /** Value for <input type="datetime-local"> from an ISO string. */
    const toLocalInput = (value: string | null | undefined) => {
        if (!value) return '';
        const d = new Date(value);
        const pad = (n: number) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };

    return { currency, number, date, datetime, relative, toLocalInput };
}
