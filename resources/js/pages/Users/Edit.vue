<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
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
    partnerOnboardingStatus: string | null;
    salesPartnerProfile: {
        legal_name: string;
        mobile: string;
        working_location: string | null;
        current_company_name: string | null;
        pan_number: string | null;
        aadhar_number: string | null;
        bank_name: string | null;
        bank_account_number: string | null;
        ifsc_code: string | null;
        onboarding_completed_at: string | null;
    } | null;
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

const isSalesPartner = computed(() => form.role === 'Sales Partner' || props.user.role === 'Sales Partner');

const onboardingStatusLabel = computed(() => {
    switch (props.partnerOnboardingStatus) {
        case 'pending':
            return 'Pending onboarding';
        case 'onboarded':
            return 'Onboarded';
        case 'skipped':
            return 'Onboarding skipped';
        default:
            return '';
    }
});

function display(value?: string | null): string {
    return value?.trim() ? value : '—';
}

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

        <CrmPanel v-if="isSalesPartner">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Sales Partner profile</h2>
                <span
                    v-if="partnerOnboardingStatus"
                    class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                >
                    {{ onboardingStatusLabel }}
                </span>
            </div>

            <div v-if="salesPartnerProfile" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">Name (PAN / Aadhaar)</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.legal_name) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Mobile</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.mobile) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Working location</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.working_location) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Current company</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.current_company_name) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">PAN</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.pan_number) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Aadhaar</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.aadhar_number) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Bank name</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.bank_name) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Account number</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.bank_account_number) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">IFSC</p>
                    <p class="mt-1 text-sm font-medium">{{ display(salesPartnerProfile.ifsc_code) }}</p>
                </div>
                <div v-if="salesPartnerProfile.onboarding_completed_at">
                    <p class="text-xs text-muted-foreground">Onboarding completed</p>
                    <p class="mt-1 text-sm font-medium">{{ formatDateTime(salesPartnerProfile.onboarding_completed_at) }}</p>
                </div>
            </div>

            <p v-else class="text-sm text-muted-foreground">
                <template v-if="partnerOnboardingStatus === 'pending'">
                    This partner has not completed onboarding yet. Profile details will appear here once they submit the onboarding form.
                </template>
                <template v-else>
                    No onboarding profile on file. This partner was created with onboarding skipped or before onboarding was enabled.
                </template>
            </p>
        </CrmPanel>

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
