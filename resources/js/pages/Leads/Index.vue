<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import leadsRoute from '@/routes/leads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Leads', href: leadsRoute.index() },
        ],
    },
});

const props = defineProps<{
    leads: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            company_name: string | null;
            status: string;
            product: string | null;
            source: string;
            created_at: string;
            assignee?: { name: string } | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { status?: string; search?: string; product?: string; sort?: string; direction?: string };
    statuses: Array<{ value: string; label: string }>;
    products: Array<{ value: string; label: string }>;
    canCreate: boolean;
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const product = ref(props.filters.product ?? '');
const sort = ref(props.filters.sort ?? 'created_at');
const direction = ref(props.filters.direction ?? 'desc');

function applyFilters() {
    router.get(
        leadsRoute.index.url({
            query: {
                search: search.value || undefined,
                status: status.value || undefined,
                product: product.value || undefined,
                sort: sort.value,
                direction: direction.value,
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function toggleSort(column: string) {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    applyFilters();
}
</script>

<template>
    <Head title="Leads" />

    <div class="crm-page">
        <CrmPageHeader title="Leads" description="Manage inbound leads and onboarding invitations">
            <template #actions>
                <Button v-if="canCreate" as-child variant="secondary" class="rounded-xl">
                    <Link :href="leadsRoute.create()">+ New Lead</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm">Search</label>
                    <input
                        v-model="search"
                        type="search"
                        class="crm-input min-w-[220px]"
                        placeholder="Name, email, company..."
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm">Status</label>
                    <select v-model="status" class="crm-input min-w-[160px]" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm">Product</label>
                    <select v-model="product" class="crm-input min-w-[160px]" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <button type="button" class="crm-btn-primary" @click="applyFilters">Filter</button>
            </div>
        </CrmPanel>

        <CrmPanel flush class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="crm-table">
                    <thead class="crm-table-head">
                        <tr>
                            <th class="cursor-pointer" @click="toggleSort('name')">Name</th>
                            <th class="cursor-pointer" @click="toggleSort('company_name')">Company</th>
                            <th class="cursor-pointer" @click="toggleSort('status')">Status</th>
                            <th>Product</th>
                            <th>Source</th>
                            <th>Assigned</th>
                            <th class="cursor-pointer" @click="toggleSort('created_at')">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="props.leads.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No leads found.</td>
                        </tr>
                        <tr v-for="lead in props.leads.data" :key="lead.id" class="crm-table-row">
                            <td>
                                <Link :href="leadsRoute.show({ lead: lead.id })" class="font-semibold text-primary hover:underline">
                                    {{ lead.name }}
                                </Link>
                                <div class="text-xs text-muted-foreground">{{ lead.email }}</div>
                            </td>
                            <td>{{ lead.company_name || '—' }}</td>
                            <td class="capitalize">{{ lead.status.replace(/_/g, ' ') }}</td>
                            <td class="capitalize">{{ lead.product || '—' }}</td>
                            <td>{{ lead.source }}</td>
                            <td>{{ lead.assignee?.name || '—' }}</td>
                            <td class="text-muted-foreground">{{ new Date(lead.created_at).toLocaleDateString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CrmPanel>
    </div>
</template>
