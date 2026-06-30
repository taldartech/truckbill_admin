<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import subscriptionsRoute from '@/routes/subscriptions';
import { formatDate } from '@/lib/dates';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subscriptions', href: subscriptionsRoute.pricing() },
            { title: 'Promo codes', href: subscriptionsRoute.promoCodes() },
        ],
    },
});

const props = defineProps<{
    promo_codes: Array<{
        id: number;
        code: string;
        discount_type: string;
        discount_value: number;
        applicable_products: string[];
        start_date: string;
        end_date: string | null;
        max_uses: number | null;
        uses_count: number;
        is_active: boolean;
    }>;
    products: Array<{ value: string; label: string }>;
}>();

function productLabel(value: string) {
    return props.products.find((p) => p.value === value)?.label ?? value;
}

function formatDiscount(promo: { discount_type: string; discount_value: number }) {
    if (promo.discount_type === 'percentage') {
        return `${promo.discount_value}%`;
    }

    return `₹${Number(promo.discount_value).toFixed(2)}`;
}

function formatValidPeriod(promo: { start_date: string; end_date: string | null }) {
    const start = formatDate(promo.start_date);
    const end = promo.end_date ? formatDate(promo.end_date) : null;

    return end && end !== '—' ? `${start} – ${end}` : `${start} – No end`;
}
</script>

<template>
    <Head title="Promo Codes" />

    <div class="crm-page">
        <CrmPageHeader title="Promo codes" description="Global codes with per-product applicability">
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="subscriptionsRoute.promoCodes.create()">+ New promo code</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel flush class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="crm-table">
                    <thead class="crm-table-head">
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Products</th>
                            <th>Valid period</th>
                            <th>Uses</th>
                            <th>Max usage</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="promo_codes.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No promo codes yet.</td>
                        </tr>
                        <tr v-for="promo in promo_codes" :key="promo.id" class="crm-table-row">
                            <td class="font-semibold">{{ promo.code }}</td>
                            <td>{{ formatDiscount(promo) }}</td>
                            <td>{{ promo.applicable_products.map(productLabel).join(', ') }}</td>
                            <td class="text-muted-foreground">
                                {{ formatValidPeriod(promo) }}
                            </td>
                            <td>{{ promo.uses_count }}</td>
                            <td>{{ promo.max_uses ?? 'Unlimited' }}</td>
                            <td>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="promo.is_active ? 'bg-emerald-500/10 text-emerald-700' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ promo.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <Link
                                    :href="subscriptionsRoute.promoCodes.edit({ promoCode: promo.id })"
                                    class="font-medium text-secondary hover:underline"
                                >
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
