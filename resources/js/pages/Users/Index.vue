<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';
import usersRoute from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: usersRoute.index() },
        ],
    },
});

const props = defineProps<{
    users: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            roles: Array<{ name: string }>;
            manager?: { name: string } | null;
            assigned_leads_count: number;
        }>;
    };
    filters: { search?: string; role?: string };
    roles: string[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string });

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');

function applyFilters() {
    router.get(usersRoute.index(), { search: search.value || undefined, role: role.value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-col gap-4 p-4">
        <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ flash.success }}</div>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap gap-3">
                <input v-model="search" type="search" placeholder="Search users..." class="rounded-md border px-3 py-2 text-sm" @keyup.enter="applyFilters" />
                <select v-model="role" class="rounded-md border px-3 py-2 text-sm" @change="applyFilters">
                    <option value="">All roles</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
                <button class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" @click="applyFilters">Filter</button>
            </div>
            <Link :href="usersRoute.create()" class="rounded-md bg-secondary px-4 py-2 text-sm text-white">+ New User</Link>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Manager</th>
                        <th class="px-4 py-3">Leads</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-muted-foreground text-xs">{{ user.email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ user.roles[0]?.name || '—' }}</td>
                        <td class="px-4 py-3">{{ user.manager?.name || '—' }}</td>
                        <td class="px-4 py-3">{{ user.assigned_leads_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="usersRoute.edit({ user: user.id })" class="text-primary">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
