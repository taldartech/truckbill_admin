<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const product = ref(props.filters.product ?? '');
const sort = ref(props.filters.sort ?? 'created_at');
const direction = ref(props.filters.direction ?? 'desc');

function applyFilters() {
    router.get(
        leadsRoute.index(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            product: product.value || undefined,
            sort: sort.value,
            direction: direction.value,
        },
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

    <div class="flex flex-col gap-4 p-4">
        <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">
            {{ flash.success }}
        </div>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm">Search</label>
                    <input
                        v-model="search"
                        type="search"
                        class="rounded-md border px-3 py-2 text-sm"
                        placeholder="Name, email, company..."
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm">Status</label>
                    <select v-model="status" class="rounded-md border px-3 py-2 text-sm" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm">Product</label>
                    <select v-model="product" class="rounded-md border px-3 py-2 text-sm" @change="applyFilters">
                        <option value="">All</option>
                        <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <button class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" @click="applyFilters">
                    Filter
                </button>
            </div>
            <Link
                v-if="canCreate"
                :href="leadsRoute.create()"
                class="rounded-md bg-secondary px-4 py-2 text-sm text-white"
            >
                + New Lead
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <table class="min-w-full text-sm">
                <thead class="border-b bg-muted/40 text-left">
                    <tr>
                        <th class="cursor-pointer px-4 py-3" @click="toggleSort('name')">Name</th>
                        <th class="cursor-pointer px-4 py-3" @click="toggleSort('company_name')">Company</th>
                        <th class="cursor-pointer px-4 py-3" @click="toggleSort('status')">Status</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Assigned</th>
                        <th class="cursor-pointer px-4 py-3" @click="toggleSort('created_at')">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="props.leads.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">No leads found.</td>
                    </tr>
                    <tr v-for="lead in props.leads.data" :key="lead.id" class="border-b hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <Link :href="leadsRoute.show({ lead: lead.id })" class="font-medium text-primary">
                                {{ lead.name }}
                            </Link>
                            <div class="text-muted-foreground text-xs">{{ lead.email }}</div>
                        </td>
                        <td class="px-4 py-3">{{ lead.company_name || '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ lead.status.replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3 capitalize">{{ lead.product || '—' }}</td>
                        <td class="px-4 py-3">{{ lead.source }}</td>
                        <td class="px-4 py-3">{{ lead.assignee?.name || '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ new Date(lead.created_at).toLocaleDateString() }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
