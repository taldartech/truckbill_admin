<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { dashboard } from '@/routes';
import rolesRoute from '@/routes/roles';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Roles', href: rolesRoute.index() },
        ],
    },
});

const props = defineProps<{
    roles: Array<{ id: number; name: string; permissions: string[]; users_count: number }>;
    permissions: string[];
}>();

const permissionState = reactive<Record<number, string[]>>(
    Object.fromEntries(props.roles.map((r) => [r.id, [...r.permissions]])),
);

const saving = reactive<Record<number, boolean>>({});

function saveRole(role: { id: number; name: string }) {
    if (role.name === 'Super Admin') return;

    saving[role.id] = true;
    router.patch(
        rolesRoute.update({ role: role.id }).url,
        { permissions: permissionState[role.id] },
        {
            preserveScroll: true,
            onFinish: () => {
                saving[role.id] = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Roles" />

    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-2xl font-semibold">Roles & Permissions</h1>

        <div v-for="role in roles" :key="role.id" class="rounded-xl border bg-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">{{ role.name }}</h2>
                    <p class="text-muted-foreground text-sm">{{ role.users_count }} user(s)</p>
                </div>
                <button
                    v-if="role.name !== 'Super Admin'"
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
                    :disabled="saving[role.id]"
                    @click="saveRole(role)"
                >
                    Save
                </button>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="perm in permissions" :key="perm" class="flex items-center gap-2 text-sm">
                    <input
                        v-model="permissionState[role.id]"
                        type="checkbox"
                        :value="perm"
                        :disabled="role.name === 'Super Admin'"
                        class="rounded border"
                    />
                    {{ perm }}
                </label>
            </div>
        </div>
    </div>
</template>
