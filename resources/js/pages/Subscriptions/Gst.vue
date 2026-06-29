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
            { title: 'GST', href: subscriptionsRoute.gst() },
        ],
    },
});

const props = defineProps<{
    rate_percent: number;
    effective_from: string | null;
}>();

const form = useForm({ rate_percent: props.rate_percent });

function submit() {
    form.patch(subscriptionsRoute.gst.update().url);
}
</script>

<template>
    <Head title="GST Settings" />
    <div class="crm-page">
        <CrmPageHeader title="GST rate" description="Applied to subscription checkout totals" />
        <CrmPanel>
            <form class="max-w-sm space-y-3" @submit.prevent="submit">
                <label class="block text-sm font-medium">Rate (%)
                    <input v-model.number="form.rate_percent" class="crm-input mt-1 w-full" step="0.01" type="number" />
                </label>
                <p v-if="effective_from" class="text-xs text-muted-foreground">Current effective from {{ effective_from }}</p>
                <Button type="submit" :disabled="form.processing">Update GST</Button>
            </form>
        </CrmPanel>
    </div>
</template>
