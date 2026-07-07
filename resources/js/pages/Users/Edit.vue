<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
            { title: 'Edit', href: '#' },
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
    <Head :title="`Edit ${user.name}`" />

    <div class="crm-page space-y-4">
        <CrmPageHeader :title="`Edit ${user.name}`" description="Update account details and role assignment">
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
                        New password
                        <input v-model="form.password" type="password" class="crm-input mt-1 w-full" />
                        <p class="mt-1 text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                    </label>

                    <label class="text-sm">
                        Confirm password
                        <input v-model="form.password_confirmation" type="password" class="crm-input mt-1 w-full" />
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
                    <Button type="submit" class="rounded-xl" :disabled="form.processing">Save changes</Button>
                    <Button as-child variant="secondary" class="rounded-xl">
                        <Link :href="usersRoute.index()">Cancel</Link>
                    </Button>
                </div>
            </form>
        </CrmPanel>

        <CrmPanel v-if="canDelete">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-destructive">Delete user</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Permanently remove this account from the CRM.</p>
                </div>
                <Button type="button" variant="destructive" class="rounded-xl" @click="destroy">Delete user</Button>
            </div>
        </CrmPanel>
    </div>
</template>
