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
            { title: 'Settings', href: '/subscriptions/settings' },
        ],
    },
});

const props = defineProps<{
    grace_period_days: number;
    gst_rate_percent: number;
    effective_from: string | null;
}>();

const form = useForm({
    grace_period_days: props.grace_period_days,
    gst_rate_percent: props.gst_rate_percent,
});

function submit() {
    form.patch('/subscriptions/settings');
}
</script>

<template>
    <Head title="Subscription Settings" />
    <div class="crm-page">
        <CrmPageHeader
            title="Subscription settings"
            description="Global policy applied to all products"
        />
        <CrmPanel>
            <form class="max-w-md space-y-5" @submit.prevent="submit">
                <label class="block text-sm font-medium">
                    Grace period (days)
                    <input
                        v-model.number="form.grace_period_days"
                        class="crm-input mt-1 w-full"
                        min="0"
                        step="1"
                        type="number"
                    />
                    <span class="mt-1 block text-xs text-muted-foreground">
                        View-only access after subscription expiry. Set to 0 to lock immediately.
                    </span>
                </label>

                <label class="block text-sm font-medium">
                    GST rate (%)
                    <input
                        v-model.number="form.gst_rate_percent"
                        class="crm-input mt-1 w-full"
                        min="0"
                        max="100"
                        step="0.01"
                        type="number"
                    />
                    <span class="mt-1 block text-xs text-muted-foreground">
                        Applied to subscription checkout totals.
                    </span>
                </label>

                <p v-if="effective_from" class="text-xs text-muted-foreground">
                    Current effective from {{ effective_from }}
                </p>

                <Button type="submit" :disabled="form.processing">Save settings</Button>
            </form>
        </CrmPanel>
    </div>
</template>
