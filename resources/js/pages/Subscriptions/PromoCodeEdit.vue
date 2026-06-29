<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
            { title: 'Promo codes', href: subscriptionsRoute.promoCodes() },
            { title: 'Edit', href: '#' },
        ],
    },
});

const props = defineProps<{
    promo_code: {
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
    };
    products: Array<{ value: string; label: string }>;
    discount_types: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    code: props.promo_code.code,
    discount_type: props.promo_code.discount_type,
    discount_value: props.promo_code.discount_value,
    applicable_products: [...props.promo_code.applicable_products],
    start_date: props.promo_code.start_date,
    end_date: props.promo_code.end_date ?? '',
    max_uses: props.promo_code.max_uses,
    is_active: props.promo_code.is_active,
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            end_date: data.end_date || null,
            max_uses: data.max_uses ? Number(data.max_uses) : null,
        }))
        .patch(subscriptionsRoute.promoCodes.update({ promoCode: props.promo_code.id }).url);
}
</script>

<template>
    <Head :title="`Edit ${promo_code.code}`" />

    <div class="crm-page">
        <CrmPageHeader title="Edit promo code" :description="`Update ${promo_code.code}`">
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="subscriptionsRoute.promoCodes()">Back to promo codes</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <form class="mx-auto max-w-2xl space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Code *</label>
                        <input v-model="form.code" class="crm-input w-full uppercase" placeholder="FQ9K2X" required />
                        <p v-if="form.errors.code" class="mt-1 text-sm text-destructive">{{ form.errors.code }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Discount type *</label>
                        <select v-model="form.discount_type" class="crm-input w-full">
                            <option v-for="t in discount_types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p v-if="form.errors.discount_type" class="mt-1 text-sm text-destructive">{{ form.errors.discount_type }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Discount value *</label>
                        <input
                            v-model.number="form.discount_value"
                            class="crm-input w-full"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :placeholder="form.discount_type === 'percentage' ? '10' : '500'"
                            required
                        />
                        <p v-if="form.errors.discount_value" class="mt-1 text-sm text-destructive">{{ form.errors.discount_value }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Start date *</label>
                        <input v-model="form.start_date" class="crm-input w-full" type="date" required />
                        <p v-if="form.errors.start_date" class="mt-1 text-sm text-destructive">{{ form.errors.start_date }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">End date</label>
                        <input v-model="form.end_date" class="crm-input w-full" type="date" />
                        <p class="mt-1 text-xs text-muted-foreground">Leave empty for no end date.</p>
                        <p v-if="form.errors.end_date" class="mt-1 text-sm text-destructive">{{ form.errors.end_date }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Maximum usage</label>
                        <input
                            v-model.number="form.max_uses"
                            class="crm-input w-full max-w-xs"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Leave empty for unlimited redemptions. Current uses: {{ promo_code.uses_count }}.
                        </p>
                        <p v-if="form.errors.max_uses" class="mt-1 text-sm text-destructive">{{ form.errors.max_uses }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium">Applicable products *</label>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="p in products" :key="p.value" class="flex items-center gap-2 text-sm">
                                <input v-model="form.applicable_products" type="checkbox" :value="p.value" />
                                {{ p.label }}
                            </label>
                        </div>
                        <p v-if="form.errors.applicable_products" class="mt-1 text-sm text-destructive">{{ form.errors.applicable_products }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" />
                            Active
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">Save changes</Button>
                    <Button as-child variant="secondary" class="rounded-xl">
                        <Link :href="subscriptionsRoute.promoCodes()">Cancel</Link>
                    </Button>
                </div>
            </form>
        </CrmPanel>
    </div>
</template>
