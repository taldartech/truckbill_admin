<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import usersRoute from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Users', href: usersRoute.index() },
        ],
    },
});

const props = defineProps<{
    user: { id: number; name: string; email: string; manager_id: number | null; role: string | null };
    roles: Array<{ id: number; name: string }>;
    managers: Array<{ id: number; name: string }>;
    canDelete: boolean;
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role || 'Sales Executive',
    manager_id: props.user.manager_id,
});

function submit() {
    form.patch(usersRoute.update({ user: props.user.id }).url);
}

function destroy() {
    if (confirm('Delete this user?')) {
        router.delete(usersRoute.destroy({ user: props.user.id }).url);
    }
}
</script>

<template>
    <Head title="Edit User" />

    <div class="mx-auto max-w-lg p-4">
        <h1 class="mb-6 text-2xl font-semibold">Edit User</h1>

        <form class="space-y-4 rounded-xl border bg-card p-6" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm">Name *</label>
                <input v-model="form.name" required class="w-full rounded-md border px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-sm">Email *</label>
                <input v-model="form.email" type="email" required class="w-full rounded-md border px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-sm">New Password (leave blank to keep)</label>
                <input v-model="form.password" type="password" class="w-full rounded-md border px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-sm">Confirm Password</label>
                <input v-model="form.password_confirmation" type="password" class="w-full rounded-md border px-3 py-2 text-sm" />
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
                <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground" :disabled="form.processing">Save</button>
                <Link :href="usersRoute.index()" class="rounded-md border px-4 py-2 text-sm">Cancel</Link>
            </div>
        </form>

        <button
            v-if="canDelete"
            type="button"
            class="mt-4 w-full rounded-md border border-destructive py-2 text-sm text-destructive"
            @click="destroy"
        >
            Delete User
        </button>
    </div>
</template>
