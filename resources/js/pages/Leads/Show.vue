<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { followUpLabel, followUpStatusFromDate, formatDate, formatDateTime, toDateInputValue } from '@/lib/dates';
import { dashboard } from '@/routes';
import leadsRoute from '@/routes/leads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Leads', href: leadsRoute.index() },
        ],
    },
});

const props = defineProps<{
    lead: {
        id: number;
        name: string;
        email: string;
        mobile: string | null;
        company_name: string | null;
        city: string | null;
        state: string | null;
        status: string;
        product: string | null;
        source: string;
        remarks: string | null;
        assigned_to: number | null;
        next_follow_up_at: string | null;
        utm_source: string | null;
        utm_medium: string | null;
        utm_campaign: string | null;
        landing_url: string | null;
        referrer: string | null;
        assignee?: { name: string } | null;
        notes: Array<{ id: number; body: string; author?: { name: string } }>;
    };
    activities: Array<{
        id: number;
        event: string | null;
        description: string;
        created_at: string;
        actor: string | null;
        properties: Record<string, unknown>;
    }>;
    users: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
    products: Array<{ value: string; label: string }>;
    canDelete: boolean;
    canInvite: boolean;
    canUpdate: boolean;
    canAssign: boolean;
}>();

const updateForm = useForm({
    status: props.lead.status,
    assigned_to: props.lead.assigned_to,
    product: props.lead.product,
    remarks: props.lead.remarks,
    next_follow_up_at: toDateInputValue(props.lead.next_follow_up_at),
});

const followUpStatus = computed(() => followUpStatusFromDate(props.lead.next_follow_up_at));

const remarksRequired = computed(() => updateForm.status === 'lost');

const trafficSourceDisplay = computed(() => {
    if (props.lead.utm_source) {
        return props.lead.utm_source;
    }

    if (props.lead.referrer) {
        try {
            return new URL(props.lead.referrer).hostname || props.lead.referrer;
        } catch {
            return props.lead.referrer;
        }
    }

    return 'Direct';
});

const trafficMediumDisplay = computed(() => props.lead.utm_medium || '—');

function display(value?: string | null): string {
    return value?.trim() ? value : '—';
}

const noteForm = useForm({ body: '' });
const inviteForm = useForm({ product: props.lead.product || 'broker' });

function saveLead() {
    updateForm
        .transform((data) => {
            const base = props.canAssign ? data : (({ assigned_to: _assignedTo, ...rest }) => rest)(data);

            return {
                ...base,
                next_follow_up_at: data.next_follow_up_at || null,
            };
        })
        .patch(leadsRoute.update({ lead: props.lead.id }).url);
}

function addNote() {
    noteForm.post(leadsRoute.notes.store({ lead: props.lead.id }).url, {
        onSuccess: () => noteForm.reset(),
    });
}

function sendInvite() {
    const productLabel =
        props.products.find((p) => p.value === inviteForm.product)?.label ?? inviteForm.product;
    const confirmed = confirm(
        `Send ${productLabel} onboarding link to ${props.lead.email}?`,
    );

    if (!confirmed) {
        return;
    }

    inviteForm.post(leadsRoute.invite({ lead: props.lead.id }).url);
}

function deleteLead() {
    if (confirm('Delete this lead permanently?')) {
        router.delete(leadsRoute.destroy({ lead: props.lead.id }).url);
    }
}

function formatActivityTime(value: string) {
    return formatDateTime(value);
}
</script>

<template>
    <Head :title="`Lead: ${lead.name}`" />

    <div class="crm-page grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="crm-panel">
                <div class="crm-panel-body">
                <h1 class="text-2xl font-semibold">{{ lead.name }}</h1>
                <p class="text-muted-foreground">{{ lead.email }} · {{ lead.mobile || 'No mobile' }}</p>
                <p class="mt-2">{{ lead.company_name || 'No company' }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ [lead.city, lead.state].filter(Boolean).join(', ') || 'No location' }}
                </p>
                <p class="mt-2 text-sm">Form channel: {{ lead.source }}</p>
                <p
                    v-if="lead.next_follow_up_at"
                    class="mt-2 text-sm"
                    :class="{
                        'font-medium text-destructive': followUpStatus === 'overdue',
                        'font-medium text-amber-600': followUpStatus === 'due_today',
                    }"
                >
                    Next follow-up: {{ formatDate(lead.next_follow_up_at) }}
                    <span v-if="followUpStatus" class="text-muted-foreground">({{ followUpLabel(followUpStatus) }})</span>
                </p>
                <p v-if="lead.assignee || !canAssign" class="mt-2 text-sm text-muted-foreground">
                    Assigned to: {{ lead.assignee?.name || 'Unassigned' }}
                </p>

                <div class="mt-4 border-t border-border pt-4">
                    <h2 class="mb-3 text-sm font-semibold">Acquisition</h2>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">Source</dt>
                            <dd class="mt-1 font-medium">{{ trafficSourceDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Medium</dt>
                            <dd class="mt-1 font-medium">{{ trafficMediumDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Campaign</dt>
                            <dd class="mt-1 font-medium">{{ display(lead.utm_campaign) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">Landing URL</dt>
                            <dd class="mt-1 break-all font-medium">{{ display(lead.landing_url) }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">Referrer</dt>
                            <dd class="mt-1 break-all font-medium">{{ display(lead.referrer) }}</dd>
                        </div>
                    </dl>
                </div>
                </div>
            </section>

            <section v-if="canUpdate" class="crm-panel">
                <div class="crm-panel-body">
                <h2 class="mb-3 font-semibold">Notes</h2>
                <form class="mb-4 flex gap-2" @submit.prevent="addNote">
                    <textarea
                        v-model="noteForm.body"
                        class="min-h-20 flex-1 crm-input"
                        placeholder="Add a note..."
                    />
                    <button
                        type="submit"
                        class="crm-btn-primary self-start"
                        :disabled="noteForm.processing"
                    >
                        Add
                    </button>
                </form>
                <ul class="space-y-3">
                    <li v-for="note in lead.notes" :key="note.id" class="rounded-lg bg-muted/30 p-3 text-sm">
                        <p>{{ note.body }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ note.author?.name }} ({{ formatActivityTime(note.created_at ?? '') }})</p>
                    </li>
                </ul>
                </div>
            </section>

            <section v-else-if="lead.notes.length > 0" class="crm-panel">
                <div class="crm-panel-body">
                <h2 class="mb-3 font-semibold">Notes</h2>
                <ul class="space-y-3">
                    <li v-for="note in lead.notes" :key="note.id" class="rounded-lg bg-muted/30 p-3 text-sm">
                        <p>{{ note.body }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ note.author?.name }}</p>
                    </li>
                </ul>
                </div>
            </section>

            <section class="crm-panel">
                <div class="crm-panel-body">
                <h2 class="mb-3 font-semibold">Activity Timeline</h2>
                <ul v-if="activities.length > 0" class="space-y-4">
                    <li v-for="activity in activities" :key="activity.id" class="border-l-2 border-primary pl-3">
                        <p class="font-medium">{{ activity.description }}</p>
                        <p v-if="activity.actor" class="text-muted-foreground mt-1 text-xs">by {{ activity.actor }}</p>
                        <p class="text-muted-foreground text-xs">{{ formatActivityTime(activity.created_at) }}</p>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">No activity recorded yet.</p>
                </div>
            </section>
        </div>

        <div class="space-y-4">
            <form v-if="canUpdate" class="crm-panel space-y-3" @submit.prevent="saveLead">
                <div class="crm-panel-body space-y-3">
                <h2 class="font-semibold">Manage Lead</h2>
                <div>
                    <label class="text-sm">Status</label>
                    <select v-model="updateForm.status" class="crm-input mt-1 w-full">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div v-if="canAssign">
                    <label class="text-sm">Assigned To</label>
                    <select v-model="updateForm.assigned_to" class="crm-input mt-1 w-full">
                        <option :value="null">Unassigned</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">Product</label>
                    <select v-model="updateForm.product" class="crm-input mt-1 w-full">
                        <option :value="null">None</option>
                        <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">
                        Remarks
                        <span v-if="remarksRequired" class="text-destructive">*</span>
                    </label>
                    <textarea
                        v-model="updateForm.remarks"
                        class="crm-input mt-1 min-h-24 w-full"
                        :required="remarksRequired"
                    />
                    <p v-if="remarksRequired" class="mt-1 text-xs text-muted-foreground">
                        Required when marking the lead as Lost.
                    </p>
                    <p v-if="updateForm.errors.remarks" class="mt-1 text-sm text-destructive">{{ updateForm.errors.remarks }}</p>
                </div>
                <div>
                    <label class="text-sm">Next follow-up</label>
                    <input
                        v-model="updateForm.next_follow_up_at"
                        type="date"
                        class="crm-input mt-1 w-full"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">Leave empty to clear the scheduled follow-up.</p>
                </div>
                <button type="submit" class="crm-btn-primary w-full">Save Changes</button>
                </div>
            </form>

            <form v-if="canInvite" class="crm-panel" @submit.prevent="sendInvite">
                <div class="crm-panel-body space-y-3">
                <h2 class="font-semibold">Send Onboarding Invite</h2>
                <select v-model="inviteForm.product" class="crm-input w-full">
                    <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
                <button type="submit" class="crm-btn-secondary w-full">Send Invite</button>
                </div>
            </form>

            <button
                v-if="canDelete"
                type="button"
                class="w-full rounded-xl border border-destructive py-2.5 text-sm font-medium text-destructive transition hover:bg-destructive/5"
                @click="deleteLead"
            >
                Delete Lead
            </button>
        </div>
    </div>
</template>
