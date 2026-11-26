import axios from 'axios';

/**
 * JSON client for non-navigational requests (search, availability lookups,
 * live previews, scanning). Sends the XSRF cookie automatically.
 */
export const http = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

export function errorMessage(error: unknown, fallback = 'Something went wrong.'): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
        return first ?? data?.message ?? fallback;
    }
    return fallback;
}
