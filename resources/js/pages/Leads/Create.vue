<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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

    <div class="mx-auto max-w-2xl p-4">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Create Lead</h1>
            <Link :href="leadsRoute.index()" class="text-sm text-primary">Back to leads</Link>
        </div>

        <form class="space-y-4 rounded-xl border bg-card p-6" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Name *</label>
                    <input v-model="form.name" type="text" required class="w-full rounded-md border px-3 py-2 text-sm" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Email *</label>
                    <input v-model="form.email" type="email" required class="w-full rounded-md border px-3 py-2 text-sm" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Mobile</label>
                    <input v-model="form.mobile" type="tel" class="w-full rounded-md border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Company</label>
                    <input v-model="form.company_name" type="text" class="w-full rounded-md border px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Product</label>
                    <select v-model="form.product" class="w-full rounded-md border px-3 py-2 text-sm">
                        <option value="">None</option>
                        <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Status</label>
                    <select v-model="form.status" class="w-full rounded-md border px-3 py-2 text-sm">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Assign To</label>
                    <select v-model="form.assigned_to" class="w-full rounded-md border px-3 py-2 text-sm">
                        <option :value="null">Unassigned</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Remarks</label>
                    <textarea v-model="form.remarks" rows="3" class="w-full rounded-md border px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" :disabled="form.processing">
                    Create Lead
                </button>
                <Link :href="leadsRoute.index()" class="rounded-md border px-4 py-2 text-sm">Cancel</Link>
            </div>
        </form>
    </div>
</template>
