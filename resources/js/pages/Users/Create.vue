<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
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

    <div class="crm-page">
        <CrmPageHeader title="Create user" description="Add a new CRM team member">
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="usersRoute.index()">Back to users</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <CrmPanel>
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm">
                        Name *
                        <input v-model="form.name" required class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</p>
                    </label>

                    <label class="text-sm md:col-span-2">
                        Email *
                        <input v-model="form.email" type="email" required class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-destructive">{{ form.errors.email }}</p>
                    </label>

                    <label class="text-sm">
                        Password *
                        <input v-model="form.password" type="password" required class="crm-input mt-1 w-full" />
                    </label>

                    <label class="text-sm">
                        Confirm password *
                        <input v-model="form.password_confirmation" type="password" required class="crm-input mt-1 w-full" />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-destructive">{{ form.errors.password }}</p>
                    </label>

                    <label class="text-sm">
                        Role *
                        <select v-model="form.role" class="crm-input mt-1 w-full">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-sm text-destructive">{{ form.errors.role }}</p>
                    </label>

                    <label class="text-sm">
                        Manager
                        <select v-model="form.manager_id" class="crm-input mt-1 w-full">
                            <option :value="null">None</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                        <p v-if="form.errors.manager_id" class="mt-1 text-sm text-destructive">{{ form.errors.manager_id }}</p>
                    </label>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">Create user</Button>
                    <Button as-child variant="secondary" class="rounded-xl">
                        <Link :href="usersRoute.index()">Cancel</Link>
                    </Button>
                </div>
            </form>
        </CrmPanel>
    </div>
</template>
