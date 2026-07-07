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
            { title: 'Create', href: subscriptionsRoute.promoCodes.create() },
        ],
    },
});

defineProps<{
    products: Array<{ value: string; label: string }>;
    discount_types: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    code: '',
    discount_type: 'percentage',
    discount_value: 10,
    applicable_products: ['broker'] as string[],
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '' as string | null,
    max_uses: null as number | null,
    is_active: true,
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            end_date: data.end_date || null,
            max_uses: data.max_uses ? Number(data.max_uses) : null,
        }))
        .post(subscriptionsRoute.promoCodes.store().url);
}
</script>

<template>
    <Head title="Create Promo Code" />

    <div class="crm-page">
        <CrmPageHeader title="Create promo code" description="Add a new discount code for subscription checkout">
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="subscriptionsRoute.promoCodes()">Back to promo codes</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
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

                    <div>
                        <label class="mb-1 block text-sm font-medium">Maximum usage</label>
                        <input
                            v-model.number="form.max_uses"
                            class="crm-input w-full"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">Leave empty for unlimited redemptions.</p>
                        <p v-if="form.errors.max_uses" class="mt-1 text-sm text-destructive">{{ form.errors.max_uses }}</p>
                    </div>

                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="mb-2 block text-sm font-medium">Applicable products *</label>
                        <div class="flex flex-wrap gap-4">
                            <label v-for="p in products" :key="p.value" class="flex items-center gap-2 text-sm">
                                <input v-model="form.applicable_products" type="checkbox" :value="p.value" />
                                {{ p.label }}
                            </label>
                        </div>
                        <p v-if="form.errors.applicable_products" class="mt-1 text-sm text-destructive">{{ form.errors.applicable_products }}</p>
                    </div>

                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" />
                            Active
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">Create promo code</Button>
                    <Button as-child variant="secondary" class="rounded-xl">
                        <Link :href="subscriptionsRoute.promoCodes()">Cancel</Link>
                    </Button>
                </div>
            </form>
        </CrmPanel>
    </div>
</template>
