import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const permissions = computed(
        () => (usePage().props.auth as { permissions?: string[] })?.permissions ?? [],
    );

    function can(permission: string): boolean {
        return permissions.value.includes(permission);
    }

    return { permissions, can };
}
