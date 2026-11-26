import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/** Database-driven permission checks mirrored on the client (server is authoritative). */
export function usePermissions() {
    const page = usePage<SharedProps>();
    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const isSuperAdmin = computed(() => page.props.auth?.user?.is_super_admin ?? false);

    const can = (key: string): boolean => isSuperAdmin.value || permissions.value.includes('*') || permissions.value.includes(key);
    const canAny = (...keys: string[]): boolean => keys.some(can);

    return { can, canAny, isSuperAdmin, permissions };
}
