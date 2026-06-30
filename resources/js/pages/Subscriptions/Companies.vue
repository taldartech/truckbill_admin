<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { dashboard } from '@/routes';
import subscriptionsRoute from '@/routes/subscriptions';
import { formatDate } from '@/lib/dates';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subscriptions', href: subscriptionsRoute.pricing() },
            { title: 'Companies', href: subscriptionsRoute.companies() },
        ],
    },
});

defineProps<{
    companies: Array<{
        id: number;
        name: string;
        product: string;
        product_label: string;
        status?: string;
        ends_on?: string;
    }>;
    products: Array<{ value: string; label: string }>;
}>();

function formatStatus(status?: string) {
    if (!status) {
        return '—';
    }

    return status.replace(/_/g, ' ');
}
</script>

<template>
    <Head title="Subscription Companies" />

    <div class="crm-page">
        <CrmPageHeader title="Companies" description="Subscription status across products" />

        <CrmPanel flush class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="crm-table">
                    <thead class="crm-table-head">
                        <tr>
                            <th>Product</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Ends on</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="companies.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No companies found.</td>
                        </tr>
                        <tr v-for="row in companies" :key="`${row.product}-${row.id}`" class="crm-table-row">
                            <td>{{ row.product_label }}</td>
                            <td class="font-semibold">{{ row.name }}</td>
                            <td class="capitalize">{{ formatStatus(row.status) }}</td>
                            <td class="text-muted-foreground">{{ formatDate(row.ends_on) }}</td>
                            <td class="text-right">
                                <Link
                                    :href="subscriptionsRoute.companies.show({ product: row.product, id: row.id })"
                                    class="font-medium text-primary hover:underline"
                                >
                                    Manage
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CrmPanel>
    </div>
</template>
