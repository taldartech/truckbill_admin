<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard, pricingCalculator } from '@/routes';
import pricingCalculatorRoutes from '@/routes/pricing-calculator';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Pricing calculator', href: pricingCalculator() },
        ],
    },
});

type CatalogRow = {
    product: string;
    label: string;
    base_price: number;
    included_branches: number;
    users_per_branch: number;
    included_lr_credits: number;
    included_trucks: number;
    addon_branch_price: number;
    addon_user_price: number;
    addon_truck_price: number;
    features: { lr_credits: boolean; trucks: boolean };
};

type QuoteResult = {
    line_items: Array<{ label: string; amount: number }>;
    subtotal: number;
    gst_amount: number;
    total_amount: number;
    gst_rate_percent: number;
    included: {
        branches: number;
        users_per_branch: number;
        lr_credits: number;
        trucks: number;
    };
};

const props = defineProps<{
    catalog: CatalogRow[];
    gst_rate_percent: number;
    products: Array<{ value: string; label: string }>;
}>();

const product = ref(props.catalog[0]?.product ?? props.products[0]?.value ?? 'broker');
const addonBranches = ref(0);
const addonUsers = ref(0);
const addonTrucks = ref(0);
const addonLrCredits = ref(0);

const quote = ref<QuoteResult | null>(null);
const loading = ref(false);
const error = ref('');

const selectedCatalog = computed(() => props.catalog.find((row) => row.product === product.value) ?? null);

const features = computed(() => selectedCatalog.value?.features ?? { lr_credits: false, trucks: false });

function formatMoney(amount: number) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(amount);
}

function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function calculate() {
    loading.value = true;
    error.value = '';

    try {
        const response = await fetch(pricingCalculatorRoutes.quote.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                product: product.value,
                addon_branches: addonBranches.value,
                addon_users: addonUsers.value,
                addon_trucks: addonTrucks.value,
                addon_lr_credits: addonLrCredits.value,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message || 'Unable to calculate quote.';
            quote.value = null;
            return;
        }

        quote.value = data;
    } catch {
        error.value = 'Unable to calculate quote.';
        quote.value = null;
    } finally {
        loading.value = false;
    }
}

watch(product, () => {
    addonBranches.value = 0;
    addonUsers.value = 0;
    addonTrucks.value = 0;
    addonLrCredits.value = 0;
    quote.value = null;
    error.value = '';
});

calculate();
</script>

<template>
    <Head title="Pricing calculator" />

    <div class="crm-page space-y-4">
        <CrmPageHeader
            title="Pricing calculator"
            description="Estimate annual subscription pricing across products"
        />

        <div class="grid gap-4 lg:grid-cols-3">
            <CrmPanel class="lg:col-span-2">
                <form class="space-y-4" @submit.prevent="calculate">
                    <div>
                        <label class="text-sm">
                            Product *
                            <select v-model="product" class="crm-input mt-1 w-full max-w-md">
                                <option v-for="p in catalog" :key="p.product" :value="p.product">{{ p.label }}</option>
                            </select>
                        </label>
                    </div>

                    <div v-if="selectedCatalog" class="rounded-xl border border-border bg-muted/30 p-4 text-sm">
                        <p class="font-medium">Included in base plan</p>
                        <ul class="mt-2 grid gap-1 text-muted-foreground sm:grid-cols-2">
                            <li>Base price: {{ formatMoney(selectedCatalog.base_price) }}/yr</li>
                            <li>Branches: {{ selectedCatalog.included_branches }}</li>
                            <li>Users / branch: {{ selectedCatalog.users_per_branch }}</li>
                            <li v-if="features.lr_credits">LR credits: {{ selectedCatalog.included_lr_credits }}</li>
                            <li v-if="features.trucks">Vehicles: {{ selectedCatalog.included_trucks }}</li>
                        </ul>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <label class="text-sm">
                            Extra branches
                            <input v-model.number="addonBranches" type="number" min="0" class="crm-input mt-1 w-full" />
                            <span class="mt-1 block text-xs text-muted-foreground">
                                {{ formatMoney(selectedCatalog?.addon_branch_price ?? 0) }} / branch / yr
                            </span>
                        </label>

                        <label class="text-sm">
                            Extra users
                            <input v-model.number="addonUsers" type="number" min="0" class="crm-input mt-1 w-full" />
                            <span class="mt-1 block text-xs text-muted-foreground">
                                {{ formatMoney(selectedCatalog?.addon_user_price ?? 0) }} / user / yr
                            </span>
                        </label>

                        <label v-if="features.trucks" class="text-sm">
                            Extra vehicles
                            <input v-model.number="addonTrucks" type="number" min="0" class="crm-input mt-1 w-full" />
                            <span class="mt-1 block text-xs text-muted-foreground">
                                {{ formatMoney(selectedCatalog?.addon_truck_price ?? 0) }} / vehicle / yr
                            </span>
                        </label>

                        <label v-if="features.lr_credits" class="text-sm">
                            Extra LR credits
                            <input v-model.number="addonLrCredits" type="number" min="0" class="crm-input mt-1 w-full" />
                            <span class="mt-1 block text-xs text-muted-foreground">Currently billed at ₹0</span>
                        </label>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                        <Button type="submit" class="rounded-xl" :disabled="loading">
                            {{ loading ? 'Calculating…' : 'Calculate' }}
                        </Button>
                    </div>
                </form>
            </CrmPanel>

            <CrmPanel title="Quote summary">
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-else-if="!quote" class="text-sm text-muted-foreground">Enter add-ons and calculate a quote.</p>

                <template v-else>
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="(item, index) in quote.line_items"
                            :key="`${item.label}-${index}`"
                            class="flex items-start justify-between gap-3"
                        >
                            <span class="text-muted-foreground">{{ item.label }}</span>
                            <span class="font-medium tabular-nums">{{ formatMoney(item.amount) }}</span>
                        </li>
                    </ul>

                    <div class="mt-4 space-y-2 border-t border-border pt-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums">{{ formatMoney(quote.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">GST ({{ quote.gst_rate_percent }}%)</span>
                            <span class="tabular-nums">{{ formatMoney(quote.gst_amount) }}</span>
                        </div>
                        <div class="flex justify-between gap-3 text-base font-semibold">
                            <span>Total</span>
                            <span class="tabular-nums">{{ formatMoney(quote.total_amount) }}</span>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-muted-foreground">
                        Estimate for a new annual subscription. Actual renewal quotes may differ based on current usage.
                    </p>
                </template>
            </CrmPanel>
        </div>
    </div>
</template>
