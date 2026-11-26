/**
 * Serialize a datetime-local value as an instant. Keep the original instant
 * when unchanged, including seconds and the offset during a DST fall-back.
 */
export function localDateTimeToUtc(value: string, initialValue: string, original?: string | null): string {
    if (!value) return '';

    const date = new Date(value === initialValue && original ? original : value);
    return Number.isNaN(date.getTime()) ? value : date.toISOString();
}
