<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import leadsRoute from '@/routes/leads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Leads', href: leadsRoute.index() },
            { title: 'Create', href: leadsRoute.create() },
        ],
    },
});

defineProps<{
    statuses: Array<{ value: string; label: string }>;
    products: Array<{ value: string; label: string }>;
    users: Array<{ id: number; name: string }>;
    canAssign: boolean;
}>();

const form = useForm({
    name: '',
    company_name: '',
    mobile: '',
    email: '',
    product: '',
    status: 'new',
    assigned_to: null as number | null,
    remarks: '',
});

function submit() {
    form.post(leadsRoute.store().url);
}
</script>

<template>
    <Head title="Create Lead" />

    <div class="crm-page">
        <CrmPageHeader title="Create lead" description="Add a new lead to the CRM pipeline">
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="leadsRoute.index()">Back to leads</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm md:col-span-2 lg:col-span-3">
                        Name *
                        <input v-model="form.name" type="text" required class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
                    </label>

                    <label class="text-sm">
                        Email *
                        <input v-model="form.email" type="email" required class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
                    </label>

                    <label class="text-sm">
                        Mobile
                        <input v-model="form.mobile" type="tel" class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.mobile" class="mt-1 text-sm text-destructive">{{ form.errors.mobile }}</p>
                    </label>

                    <label class="text-sm">
                        Company
                        <input v-model="form.company_name" type="text" class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.company_name" class="mt-1 text-sm text-destructive">{{ form.errors.company_name }}</p>
                    </label>

                    <label class="text-sm">
                        Product
                        <select v-model="form.product" class="crm-input mt-1 w-full">
                            <option value="">None</option>
                            <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                        <p v-if="form.errors.product" class="mt-1 text-sm text-destructive">{{ form.errors.product }}</p>
                    </label>

                    <label class="text-sm">
                        Status
                        <select v-model="form.status" class="crm-input mt-1 w-full">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-sm text-destructive">{{ form.errors.status }}</p>
                    </label>

                    <label v-if="canAssign" class="text-sm">
                        Assign to
                        <select v-model="form.assigned_to" class="crm-input mt-1 w-full">
                            <option :value="null">Unassigned</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <p v-if="form.errors.assigned_to" class="mt-1 text-sm text-destructive">{{ form.errors.assigned_to }}</p>
                    </label>

                    <label class="text-sm md:col-span-2 lg:col-span-3">
                        Remarks
                        <textarea v-model="form.remarks" rows="4" class="crm-input mt-1 min-h-24 w-full" />
                        <p v-if="form.errors.remarks" class="mt-1 text-sm text-destructive">{{ form.errors.remarks }}</p>
                    </label>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">Create lead</Button>
                    <Button as-child variant="secondary" class="rounded-xl">
                        <Link :href="leadsRoute.index()">Cancel</Link>
                    </Button>
                </div>
            </form>
        </CrmPanel>
    </div>
</template>
