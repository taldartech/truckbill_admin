<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import usersRoute from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: usersRoute.index() },
            { title: 'Create', href: usersRoute.create() },
        ],
    },
});

defineProps<{
    roles: Array<{ id: number; name: string }>;
    managers: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'Sales Executive',
    manager_id: null as number | null,
});

function submit() {
    form.post(usersRoute.store().url);
}
</script>

<template>
    <Head title="Create User" />

    <div class="mx-auto max-w-lg p-4">
        <h1 class="mb-6 text-2xl font-semibold">Create CRM User</h1>

        <form class="space-y-4 rounded-xl border bg-card p-6" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm">Name *</label>
                <input v-model="form.name" required class="w-full rounded-md border px-3 py-2 text-sm" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm">Email *</label>
                <input v-model="form.email" type="email" required class="w-full rounded-md border px-3 py-2 text-sm" />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm">Password *</label>
                <input v-model="form.password" type="password" required class="w-full rounded-md border px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-sm">Confirm Password *</label>
                <input v-model="form.password_confirmation" type="password" required class="w-full rounded-md border px-3 py-2 text-sm" />
                <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm">Role *</label>
                <select v-model="form.role" class="w-full rounded-md border px-3 py-2 text-sm">
                    <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">Manager</label>
                <select v-model="form.manager_id" class="w-full rounded-md border px-3 py-2 text-sm">
                    <option :value="null">None</option>
                    <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" :disabled="form.processing">Create</button>
                <Link :href="usersRoute.index()" class="rounded-md border px-4 py-2 text-sm">Cancel</Link>
            </div>
        </form>
    </div>
</template>
