export function customFieldValue(values: Record<string, any> | null | undefined, key: string): any {
    return values != null && Object.prototype.hasOwnProperty.call(values, key) ? values[key] : undefined;
}

export function formatCustomDecimal(value: string | number, currency?: string): string {
    return currency ? `${value} ${currency}` : String(value);
}
