<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import subscriptionsRoute from '@/routes/subscriptions';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subscriptions', href: subscriptionsRoute.pricing() },
            { title: 'Pricing', href: subscriptionsRoute.pricing() },
        ],
    },
});

const props = defineProps<{
    catalog: Array<{
        id: number;
        product: string;
        base_price: number;
        included_branches: number;
        users_per_branch: number;
        included_lr_credits: number;
        included_trucks: number;
        addon_branch_price: number;
        addon_user_price: number;
        addon_truck_price: number;
        is_active: boolean;
    }>;
    products: Array<{ value: string; label: string }>;
}>();

function formatAmount(amount: number) {
    return Number(amount).toFixed(2);
}

function updateRow(product: string) {
    const row = props.catalog.find((c) => c.product === product);
    if (!row) return;

    const form = useForm({
        base_price: row.base_price,
        included_branches: row.included_branches,
        users_per_branch: row.users_per_branch,
        included_lr_credits: row.included_lr_credits,
        included_trucks: row.included_trucks,
        addon_branch_price: row.addon_branch_price,
        addon_user_price: row.addon_user_price,
        addon_truck_price: row.addon_truck_price,
        is_active: row.is_active,
    });

    form.patch(subscriptionsRoute.pricing.update(product).url);
}
</script>

<template>
    <Head title="Subscription Pricing" />
    <div class="crm-page space-y-4">
        <CrmPageHeader title="Pricing catalog" description="Amounts in rupees with paise decimals (e.g. 25164.24)" />
        <CrmPanel v-for="row in catalog" :key="row.id" :title="products.find((p) => p.value === row.product)?.label ?? row.product">
            <div class="grid gap-3 md:grid-cols-3">
                <label class="text-sm">Base price (₹)<input v-model.number="row.base_price" class="crm-input mt-1 w-full" step="0.01" type="number" /></label>
                <label class="text-sm">Branches included<input v-model.number="row.included_branches" class="crm-input mt-1 w-full" type="number" /></label>
                <label class="text-sm">Users / branch<input v-model.number="row.users_per_branch" class="crm-input mt-1 w-full" type="number" /></label>
                <label class="text-sm">LR credits<input v-model.number="row.included_lr_credits" class="crm-input mt-1 w-full" type="number" /></label>
                <label class="text-sm">Trucks included<input v-model.number="row.included_trucks" class="crm-input mt-1 w-full" type="number" /></label>
                <label class="text-sm">Branch add-on (₹)<input v-model.number="row.addon_branch_price" class="crm-input mt-1 w-full" step="0.01" type="number" /></label>
                <label class="text-sm">User add-on (₹)<input v-model.number="row.addon_user_price" class="crm-input mt-1 w-full" step="0.01" type="number" /></label>
                <label class="text-sm">Truck add-on (₹)<input v-model.number="row.addon_truck_price" class="crm-input mt-1 w-full" step="0.01" type="number" /></label>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">Base: ₹{{ formatAmount(row.base_price) }} / year</p>
            <Button class="mt-3" type="button" @click="updateRow(row.product)">Save</Button>
        </CrmPanel>
    </div>
</template>
