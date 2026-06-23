<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
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

    <div class="crm-page">
        <CrmPageHeader
            title="Roles & Permissions"
            description="Configure access for sales and admin teams"
        />

        <CrmPanel v-for="role in roles" :key="role.id">
            <template #header>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ role.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ role.users_count }} user(s)</p>
                    </div>
                    <button
                        v-if="role.name !== 'Super Admin'"
                        type="button"
                        class="crm-btn-primary disabled:opacity-50"
                        :disabled="saving[role.id]"
                        @click="saveRole(role)"
                    >
                        Save
                    </button>
                </div>
            </template>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="perm in permissions" :key="perm" class="flex items-center gap-2 text-sm">
                    <input
                        v-model="permissionState[role.id]"
                        type="checkbox"
                        :value="perm"
                        :disabled="role.name === 'Super Admin'"
                        class="size-4 rounded border-input text-primary focus:ring-ring/30"
                    />
                    {{ perm }}
                </label>
            </div>
        </CrmPanel>
    </div>
</template>
