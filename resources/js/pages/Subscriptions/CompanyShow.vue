<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import subscriptionsRoute from '@/routes/subscriptions';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }, { title: 'Company subscription' }] },
});

const props = defineProps<{
    product: string;
    product_label: string;
    subscription: {
        company: { id: number; name: string };
        subscription: Record<string, unknown> | null;
        usage: Record<string, number>;
        payments: Array<Record<string, unknown>>;
        upcoming: Array<Record<string, unknown>>;
    };
}>();

const sub = props.subscription.subscription ?? {};

const form = useForm({
    status: (sub.status as string) ?? 'trial',
    ends_on: (sub.ends_on as string) ?? '',
    addon_branches: Number(sub.addon_branches ?? 0),
    addon_users: Number(sub.addon_users ?? 0),
    lr_credits_remaining: Number(sub.lr_credits_remaining ?? 0),
});

function submit() {
    form.patch(subscriptionsRoute.companies.update({ product: props.product, id: props.subscription.company.id }).url);
}
</script>

<template>
    <Head :title="`${subscription.company.name} subscription`" />
    <div class="crm-page space-y-4">
        <CrmPageHeader :title="subscription.company.name" :description="`${product_label} subscription override`" />
        <CrmPanel title="Usage">
            <pre class="text-xs">{{ subscription.usage }}</pre>
        </CrmPanel>
        <CrmPanel title="Override">
            <form class="grid max-w-lg gap-3" @submit.prevent="submit">
                <input v-model="form.status" class="crm-input" placeholder="status" />
                <input v-model="form.ends_on" class="crm-input" type="date" />
                <input v-model.number="form.addon_branches" class="crm-input" type="number" placeholder="addon branches" />
                <input v-model.number="form.addon_users" class="crm-input" type="number" placeholder="addon users" />
                <input v-model.number="form.lr_credits_remaining" class="crm-input" type="number" placeholder="LR credits" />
                <Button type="submit" :disabled="form.processing">Save override</Button>
            </form>
        </CrmPanel>
    </div>
</template>
