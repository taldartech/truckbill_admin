<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import subscriptionsRoute from '@/routes/subscriptions';
import { formatDate } from '@/lib/dates';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Subscriptions', href: subscriptionsRoute.pricing() },
            { title: 'Companies', href: subscriptionsRoute.companies() },
            { title: 'Manage', href: '#' },
        ],
    },
});

type SubscriptionRecord = {
    id?: number;
    status?: string;
    starts_on?: string | null;
    ends_on?: string | null;
    trial_ends_on?: string | null;
    included_branches?: number;
    users_per_branch?: number;
    included_lr_credits?: number;
    included_trucks?: number;
    addon_branches?: number;
    addon_users?: number;
    addon_trucks?: number;
    addon_lr_credits?: number;
    lr_credits_remaining?: number;
};

type PaymentRecord = {
    id: number;
    type: string;
    status: string;
    total_amount: number | string;
    created_at: string;
    razorpay_response?: {
        offline?: boolean;
        trial_extension?: boolean;
        reference?: string | null;
    } | null;
};

const props = defineProps<{
    product: string;
    product_label: string;
    subscription: {
        company: { id: number | string; name: string };
        subscription: SubscriptionRecord | null;
        usage: Record<string, number>;
        payments: PaymentRecord[];
        upcoming: Array<Record<string, unknown>>;
    };
    catalog: {
        base_price: number;
        included_branches: number;
        users_per_branch: number;
        included_lr_credits?: number;
        included_trucks?: number;
    } | null;
    features: { lr_credits: boolean; trucks: boolean };
}>();

const page = usePage();
const { can } = usePermissions();
const canManageCompanies = computed(() => can('subscriptions.companies'));
const editing = ref(false);
const quoteLoading = ref(false);
const quoteError = ref('');
const quotePreview = ref<{
    line_items?: Array<{ label: string; amount: number }>;
    subtotal?: number;
    gst_amount?: number;
    gst_rate_percent?: number;
    total_amount?: number;
    can_checkout?: boolean;
    blocking_errors?: string[];
} | null>(null);

const sub = computed(() => props.subscription.subscription ?? {});

const overrideForm = useForm({
    status: (sub.value.status as string) ?? 'trial',
    starts_on: (sub.value.starts_on as string) ?? '',
    ends_on: (sub.value.ends_on as string) ?? '',
    trial_ends_on: (sub.value.trial_ends_on as string) ?? '',
    included_branches: Number(sub.value.included_branches ?? props.catalog?.included_branches ?? 2),
    users_per_branch: Number(sub.value.users_per_branch ?? props.catalog?.users_per_branch ?? 3),
    included_lr_credits: Number(sub.value.included_lr_credits ?? props.catalog?.included_lr_credits ?? 0),
    included_trucks: Number(sub.value.included_trucks ?? props.catalog?.included_trucks ?? 0),
    addon_branches: Number(sub.value.addon_branches ?? 0),
    addon_users: Number(sub.value.addon_users ?? 0),
    addon_trucks: Number(sub.value.addon_trucks ?? 0),
    addon_lr_credits: Number(sub.value.addon_lr_credits ?? 0),
    lr_credits_remaining: Number(sub.value.lr_credits_remaining ?? 0),
    notes: '',
});

const offlineForm = useForm({
    purchase_type: 'renewal',
    addon_branches: 0,
    addon_users: 0,
    addon_trucks: 0,
    addon_lr_credits: 0,
    reference: '',
    notes: '',
    force: false,
});

const extendTrialForm = useForm({
    trial_ends_on: (sub.value.trial_ends_on as string) || (sub.value.ends_on as string) || '',
    amount: '',
    reference: '',
    notes: '',
});

function formatMoney(value: number | string | undefined) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatStatus(status?: string) {
    if (!status) return 'None';
    return status.replace(/_/g, ' ');
}

function statusClass(status?: string) {
    if (status === 'active' || status === 'trial') return 'bg-emerald-500/10 text-emerald-700';
    if (status === 'expired') return 'bg-amber-500/10 text-amber-700';
    return 'bg-muted text-muted-foreground';
}

function resetOverrideForm() {
    const current = props.subscription.subscription ?? {};
    overrideForm.status = (current.status as string) ?? 'trial';
    overrideForm.starts_on = (current.starts_on as string) ?? '';
    overrideForm.ends_on = (current.ends_on as string) ?? '';
    overrideForm.trial_ends_on = (current.trial_ends_on as string) ?? '';
    overrideForm.included_branches = Number(current.included_branches ?? props.catalog?.included_branches ?? 2);
    overrideForm.users_per_branch = Number(current.users_per_branch ?? props.catalog?.users_per_branch ?? 3);
    overrideForm.included_lr_credits = Number(current.included_lr_credits ?? props.catalog?.included_lr_credits ?? 0);
    overrideForm.included_trucks = Number(current.included_trucks ?? props.catalog?.included_trucks ?? 0);
    overrideForm.addon_branches = Number(current.addon_branches ?? 0);
    overrideForm.addon_users = Number(current.addon_users ?? 0);
    overrideForm.addon_trucks = Number(current.addon_trucks ?? 0);
    overrideForm.addon_lr_credits = Number(current.addon_lr_credits ?? 0);
    overrideForm.lr_credits_remaining = Number(current.lr_credits_remaining ?? 0);
    overrideForm.notes = '';
    overrideForm.clearErrors();
}

function startEdit() {
    resetOverrideForm();
    editing.value = true;
}

function cancelEdit() {
    resetOverrideForm();
    editing.value = false;
}

function saveOverride() {
    overrideForm.patch(
        subscriptionsRoute.companies.update({ product: props.product, id: props.subscription.company.id }).url,
        {
            onSuccess: () => {
                editing.value = false;
            },
        },
    );
}

function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function previewQuote() {
    quoteLoading.value = true;
    quoteError.value = '';
    quotePreview.value = null;

    try {
        const response = await fetch(
            subscriptionsRoute.companies.quote({ product: props.product, id: props.subscription.company.id }).url,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    purchase_type: offlineForm.purchase_type,
                    addon_branches: offlineForm.addon_branches,
                    addon_users: offlineForm.addon_users,
                    addon_trucks: offlineForm.addon_trucks,
                    addon_lr_credits: offlineForm.addon_lr_credits,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            quoteError.value = data.message || 'Unable to calculate quote.';
            return;
        }

        quotePreview.value = data;
    } catch {
        quoteError.value = 'Unable to calculate quote.';
    } finally {
        quoteLoading.value = false;
    }
}

function recordOfflinePayment() {
    offlineForm.post(
        subscriptionsRoute.companies.offlinePayment({ product: props.product, id: props.subscription.company.id }).url,
        {
            onSuccess: () => {
                offlineForm.reset();
                quotePreview.value = null;
                router.reload({ only: ['subscription'] });
            },
        },
    );
}

function extendTrial() {
    extendTrialForm.post(
        subscriptionsRoute.companies.extendTrial({ product: props.product, id: props.subscription.company.id }).url,
        {
            onSuccess: () => {
                extendTrialForm.reset('amount', 'reference', 'notes');
                router.reload({ only: ['subscription'] });
            },
        },
    );
}

function paymentLabel(payment: PaymentRecord) {
    if (payment.razorpay_response?.trial_extension) {
        return payment.razorpay_response.reference
            ? `Trial extension (${payment.razorpay_response.reference})`
            : 'Trial extension';
    }
    if (payment.razorpay_response?.offline) {
        return payment.razorpay_response.reference ? `Offline (${payment.razorpay_response.reference})` : 'Offline';
    }
    return 'Online';
}

const usageEntries = computed(() => Object.entries(props.subscription.usage ?? {}));
</script>

<template>
    <Head :title="`${subscription.company.name} subscription`" />

    <div class="crm-page space-y-4">
        <CrmPageHeader
            :title="subscription.company.name"
            :description="`${product_label} — manage subscription, trial extensions, and offline payments`"
        >
            <template #actions>
                <Button as-child variant="secondary" class="rounded-xl">
                    <Link :href="subscriptionsRoute.companies()">Back to companies</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <CrmPanel class="lg:col-span-2" title="Current plan">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Status</p>
                        <span
                            class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="statusClass(sub.status)"
                        >
                            {{ formatStatus(sub.status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Starts on</p>
                        <p class="mt-1 font-medium">{{ formatDate(sub.starts_on) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Ends on</p>
                        <p class="mt-1 font-medium">{{ formatDate(sub.ends_on) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Trial ends</p>
                        <p class="mt-1 font-medium">{{ formatDate(sub.trial_ends_on) }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-muted-foreground">Branches</p>
                        <p class="font-semibold">
                            {{ sub.included_branches ?? 0 }} + {{ sub.addon_branches ?? 0 }} add-on
                        </p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-muted-foreground">Users / branch</p>
                        <p class="font-semibold">{{ sub.users_per_branch ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-xs text-muted-foreground">Extra users</p>
                        <p class="font-semibold">{{ sub.addon_users ?? 0 }}</p>
                    </div>
                    <div v-if="features.trucks" class="rounded-lg border p-3">
                        <p class="text-xs text-muted-foreground">Vehicles</p>
                        <p class="font-semibold">
                            {{ sub.included_trucks ?? 0 }} + {{ sub.addon_trucks ?? 0 }} add-on
                        </p>
                    </div>
                    <div v-if="features.lr_credits" class="rounded-lg border p-3">
                        <p class="text-xs text-muted-foreground">LR credits left</p>
                        <p class="font-semibold">{{ sub.lr_credits_remaining ?? 0 }}</p>
                    </div>
                </div>
            </CrmPanel>

            <CrmPanel title="Usage">
                <dl class="space-y-2">
                    <div v-for="[key, value] in usageEntries" :key="key" class="flex justify-between text-sm">
                        <dt class="capitalize text-muted-foreground">{{ key.replace(/_/g, ' ') }}</dt>
                        <dd class="font-medium">{{ value }}</dd>
                    </div>
                    <p v-if="usageEntries.length === 0" class="text-sm text-muted-foreground">No usage data.</p>
                </dl>
            </CrmPanel>
        </div>

        <CrmPanel v-if="canManageCompanies">
            <template #header>
                <div class="flex w-full items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Manual plan override</h2>
                    <Button v-if="!editing" type="button" variant="secondary" size="sm" class="rounded-xl" @click="startEdit">
                        Edit
                    </Button>
                    <Button v-else type="button" variant="secondary" size="sm" class="rounded-xl" @click="cancelEdit">
                        Cancel
                    </Button>
                </div>
            </template>

            <p class="mb-4 text-sm text-muted-foreground">
                Directly adjust subscription fields without recording a payment. Use for corrections or special arrangements.
            </p>

            <form class="grid max-w-4xl gap-4 md:grid-cols-2" @submit.prevent="saveOverride">
                <label class="text-sm">
                    Status
                    <select v-model="overrideForm.status" class="crm-input mt-1 w-full" :disabled="!editing">
                        <option value="trial">Trial</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </label>
                <label class="text-sm">
                    Starts on
                    <input v-model="overrideForm.starts_on" class="crm-input mt-1 w-full" type="date" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Ends on
                    <input v-model="overrideForm.ends_on" class="crm-input mt-1 w-full" type="date" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Trial ends on
                    <input v-model="overrideForm.trial_ends_on" class="crm-input mt-1 w-full" type="date" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Included branches
                    <input v-model.number="overrideForm.included_branches" class="crm-input mt-1 w-full" type="number" min="1" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Users per branch
                    <input v-model.number="overrideForm.users_per_branch" class="crm-input mt-1 w-full" type="number" min="1" :disabled="!editing" />
                </label>
                <label v-if="features.lr_credits" class="text-sm">
                    Included LR credits
                    <input v-model.number="overrideForm.included_lr_credits" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label v-if="features.trucks" class="text-sm">
                    Included vehicles
                    <input v-model.number="overrideForm.included_trucks" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Add-on branches
                    <input v-model.number="overrideForm.addon_branches" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label class="text-sm">
                    Add-on users
                    <input v-model.number="overrideForm.addon_users" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label v-if="features.trucks" class="text-sm">
                    Add-on vehicles
                    <input v-model.number="overrideForm.addon_trucks" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label v-if="features.lr_credits" class="text-sm">
                    Add-on LR credits
                    <input v-model.number="overrideForm.addon_lr_credits" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label v-if="features.lr_credits" class="text-sm">
                    LR credits remaining
                    <input v-model.number="overrideForm.lr_credits_remaining" class="crm-input mt-1 w-full" type="number" min="0" :disabled="!editing" />
                </label>
                <label class="text-sm md:col-span-2">
                    Notes (optional)
                    <textarea v-model="overrideForm.notes" class="crm-input mt-1 w-full" rows="2" :disabled="!editing" />
                </label>
                <div class="md:col-span-2">
                    <Button type="submit" class="rounded-xl" :disabled="!editing || overrideForm.processing">Save override</Button>
                </div>
            </form>
        </CrmPanel>

        <CrmPanel v-if="canManageCompanies" title="Extend trial (chargeable)">
            <p class="mb-4 text-sm text-muted-foreground">
                Admin-only. Extends the trial access window and records the amount charged for the extension.
            </p>

            <form class="grid max-w-4xl gap-4" @submit.prevent="extendTrial">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="text-sm">
                        Trial end date
                        <input v-model="extendTrialForm.trial_ends_on" class="crm-input mt-1 w-full" type="date" required />
                        <span v-if="extendTrialForm.errors.trial_ends_on" class="mt-1 block text-sm text-destructive">
                            {{ extendTrialForm.errors.trial_ends_on }}
                        </span>
                    </label>
                    <label class="text-sm">
                        Amount charged (₹)
                        <input
                            v-model="extendTrialForm.amount"
                            class="crm-input mt-1 w-full"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="0.00"
                        />
                        <span v-if="extendTrialForm.errors.amount" class="mt-1 block text-sm text-destructive">
                            {{ extendTrialForm.errors.amount }}
                        </span>
                    </label>
                    <label class="text-sm">
                        Payment reference
                        <input v-model="extendTrialForm.reference" class="crm-input mt-1 w-full" placeholder="Cheque / UPI / receipt no." />
                    </label>
                    <label class="text-sm md:col-span-2">
                        Notes
                        <textarea v-model="extendTrialForm.notes" class="crm-input mt-1 w-full" rows="2" />
                    </label>
                </div>

                <p v-if="(page.props.errors as Record<string, string>)?.extend_trial" class="text-sm text-destructive">
                    {{ (page.props.errors as Record<string, string>).extend_trial }}
                </p>

                <Button type="submit" class="rounded-xl" :disabled="extendTrialForm.processing">Extend trial</Button>
            </form>
        </CrmPanel>

        <CrmPanel v-if="canManageCompanies" title="Record offline payment">
            <p class="mb-4 text-sm text-muted-foreground">
                For customers who paid outside Razorpay. This records the payment and applies renewal or add-on rules using current catalog pricing.
            </p>

            <form class="grid max-w-4xl gap-4" @submit.prevent="recordOfflinePayment">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="text-sm">
                        Purchase type
                        <select v-model="offlineForm.purchase_type" class="crm-input mt-1 w-full">
                            <option value="renewal">Renewal</option>
                            <option value="addon">Add-on</option>
                        </select>
                    </label>
                    <label class="text-sm">
                        Payment reference
                        <input v-model="offlineForm.reference" class="crm-input mt-1 w-full" placeholder="Cheque / UPI / receipt no." />
                    </label>
                    <label class="text-sm">
                        Extra branches
                        <input v-model.number="offlineForm.addon_branches" class="crm-input mt-1 w-full" type="number" min="0" />
                    </label>
                    <label class="text-sm">
                        Extra users
                        <input v-model.number="offlineForm.addon_users" class="crm-input mt-1 w-full" type="number" min="0" />
                    </label>
                    <label v-if="features.trucks" class="text-sm">
                        Extra vehicles
                        <input v-model.number="offlineForm.addon_trucks" class="crm-input mt-1 w-full" type="number" min="0" />
                    </label>
                    <label v-if="features.lr_credits" class="text-sm">
                        Extra LR credits
                        <input v-model.number="offlineForm.addon_lr_credits" class="crm-input mt-1 w-full" type="number" min="0" />
                    </label>
                    <label class="text-sm md:col-span-2">
                        Notes
                        <textarea v-model="offlineForm.notes" class="crm-input mt-1 w-full" rows="2" />
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" class="rounded-xl" :disabled="quoteLoading" @click="previewQuote">
                        {{ quoteLoading ? 'Calculating…' : 'Preview quote' }}
                    </Button>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="offlineForm.force" type="checkbox" />
                        Force apply (ignore capacity warnings)
                    </label>
                </div>

                <p v-if="quoteError" class="text-sm text-destructive">{{ quoteError }}</p>
                <p v-if="(page.props.errors as Record<string, string>)?.offline" class="text-sm text-destructive">
                    {{ (page.props.errors as Record<string, string>).offline }}
                </p>

                <div v-if="quotePreview" class="rounded-lg border bg-muted/30 p-4">
                    <p class="mb-2 text-sm font-medium">Quote preview</p>
                    <div v-if="quotePreview.blocking_errors?.length" class="mb-2 text-sm text-amber-700">
                        {{ quotePreview.blocking_errors.join(' ') }}
                    </div>
                    <div v-for="(item, index) in quotePreview.line_items ?? []" :key="index" class="flex justify-between py-1 text-sm">
                        <span>{{ item.label }}</span>
                        <span>₹{{ formatMoney(Math.abs(item.amount)) }}</span>
                    </div>
                    <div class="mt-2 flex justify-between border-t pt-2 text-sm">
                        <span>GST ({{ quotePreview.gst_rate_percent ?? 0 }}%)</span>
                        <span>₹{{ formatMoney(quotePreview.gst_amount) }}</span>
                    </div>
                    <div class="flex justify-between pt-1 text-sm font-semibold">
                        <span>Total</span>
                        <span>₹{{ formatMoney(quotePreview.total_amount) }}</span>
                    </div>
                </div>

                <Button type="submit" class="rounded-xl" :disabled="offlineForm.processing">Record offline payment</Button>
            </form>
        </CrmPanel>

        <div class="grid gap-4 lg:grid-cols-2">
            <CrmPanel flush class="overflow-hidden" title="Recent payments">
                <div class="overflow-x-auto">
                    <table class="crm-table">
                        <thead class="crm-table-head">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Channel</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="subscription.payments.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No payments yet.</td>
                            </tr>
                            <tr v-for="payment in subscription.payments" :key="payment.id" class="crm-table-row">
                                <td>{{ formatDate(payment.created_at?.slice(0, 10)) }}</td>
                                <td class="capitalize">{{ payment.type }}</td>
                                <td>{{ paymentLabel(payment) }}</td>
                                <td>₹{{ formatMoney(payment.total_amount) }}</td>
                                <td class="capitalize">{{ payment.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CrmPanel>

            <CrmPanel flush class="overflow-hidden" title="Pending upcoming renewals">
                <div class="overflow-x-auto">
                    <table class="crm-table">
                        <thead class="crm-table-head">
                            <tr>
                                <th>Status</th>
                                <th>Branches</th>
                                <th>Users</th>
                                <th v-if="features.trucks">Vehicles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="subscription.upcoming.length === 0">
                                <td :colspan="features.trucks ? 4 : 3" class="px-4 py-8 text-center text-muted-foreground">
                                    No pending renewals queued.
                                </td>
                            </tr>
                            <tr v-for="(row, index) in subscription.upcoming" :key="index" class="crm-table-row">
                                <td class="capitalize">{{ row.status }}</td>
                                <td>{{ row.included_branches }} + {{ row.addon_branches }}</td>
                                <td>{{ row.users_per_branch }} + {{ row.addon_users }}</td>
                                <td v-if="features.trucks">{{ row.included_trucks }} + {{ row.addon_trucks }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CrmPanel>
        </div>

        <CrmPanel v-if="catalog" title="Catalog reference">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <div>Base plan: <span class="font-medium">₹{{ formatMoney(catalog.base_price) }}/yr</span></div>
                <div>Included branches: <span class="font-medium">{{ catalog.included_branches }}</span></div>
                <div>Users / branch: <span class="font-medium">{{ catalog.users_per_branch }}</span></div>
                <div v-if="features.lr_credits">LR credits: <span class="font-medium">{{ catalog.included_lr_credits }}</span></div>
                <div v-if="features.trucks">Included vehicles: <span class="font-medium">{{ catalog.included_trucks }}</span></div>
            </div>
        </CrmPanel>
    </div>
</template>
