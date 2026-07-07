<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
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

const { can } = usePermissions();

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');

function applyFilters() {
    router.get(
        usersRoute.index.url({
            query: {
                search: search.value || undefined,
                role: role.value || undefined,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="crm-page">
        <CrmPageHeader title="Users" description="Manage CRM team members and assignments">
            <template #actions>
                <Button v-if="can('users.manage')" as-child variant="secondary" class="rounded-xl">
                    <Link :href="usersRoute.create()">+ New User</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <div class="flex flex-wrap gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search users..."
                    class="crm-input min-w-[240px]"
                    @keyup.enter="applyFilters"
                />
                <select v-model="role" class="crm-input min-w-[180px]" @change="applyFilters">
                    <option value="">All roles</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
                <button type="button" class="crm-btn-primary" @click="applyFilters">Filter</button>
            </div>
        </CrmPanel>

        <CrmPanel flush class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="crm-table">
                    <thead class="crm-table-head">
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Manager</th>
                            <th>Leads</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="crm-table-row">
                            <td>
                                <div class="font-semibold">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                            </td>
                            <td>{{ user.roles[0]?.name || '—' }}</td>
                            <td>{{ user.manager?.name || '—' }}</td>
                            <td>{{ user.assigned_leads_count }}</td>
                            <td class="text-right">
                                <Link :href="usersRoute.edit({ user: user.id })" class="font-medium text-secondary hover:underline">
                                    Edit
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CrmPanel>
    </div>
</template>
