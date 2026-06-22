<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
        status: string;
        product: string | null;
        source: string;
        remarks: string | null;
        assigned_to: number | null;
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
}>();

const updateForm = useForm({
    status: props.lead.status,
    assigned_to: props.lead.assigned_to,
    product: props.lead.product,
    remarks: props.lead.remarks,
});

const noteForm = useForm({ body: '' });
const inviteForm = useForm({ product: props.lead.product || 'broker' });

function saveLead() {
    updateForm.patch(leadsRoute.update({ lead: props.lead.id }).url);
}

function addNote() {
    noteForm.post(leadsRoute.notes.store({ lead: props.lead.id }).url, {
        onSuccess: () => noteForm.reset(),
    });
}

function sendInvite() {
    inviteForm.post(leadsRoute.invite({ lead: props.lead.id }).url);
}

function deleteLead() {
    if (confirm('Delete this lead permanently?')) {
        router.delete(leadsRoute.destroy({ lead: props.lead.id }).url);
    }
}

function formatActivityTime(value: string) {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="`Lead: ${lead.name}`" />

    <div class="grid gap-6 p-4 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border bg-card p-5">
                <h1 class="text-2xl font-semibold">{{ lead.name }}</h1>
                <p class="text-muted-foreground">{{ lead.email }} · {{ lead.mobile || 'No mobile' }}</p>
                <p class="mt-2">{{ lead.company_name || 'No company' }}</p>
                <p class="mt-2 text-sm">Source: {{ lead.source }}</p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-3 font-semibold">Notes</h2>
                <form class="mb-4 flex gap-2" @submit.prevent="addNote">
                    <textarea
                        v-model="noteForm.body"
                        class="min-h-20 flex-1 rounded-md border px-3 py-2 text-sm"
                        placeholder="Add a note..."
                    />
                    <button
                        type="submit"
                        class="self-start rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground"
                        :disabled="noteForm.processing"
                    >
                        Add
                    </button>
                </form>
                <ul class="space-y-3">
                    <li v-for="note in lead.notes" :key="note.id" class="rounded-lg bg-muted/30 p-3 text-sm">
                        <p>{{ note.body }}</p>
                        <p class="text-muted-foreground mt-1 text-xs">{{ note.author?.name }}</p>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-3 font-semibold">Activity Timeline</h2>
                <ul v-if="activities.length > 0" class="space-y-4">
                    <li v-for="activity in activities" :key="activity.id" class="border-l-2 border-primary pl-3">
                        <p class="font-medium">{{ activity.description }}</p>
                        <p v-if="activity.actor" class="text-muted-foreground mt-1 text-xs">by {{ activity.actor }}</p>
                        <p class="text-muted-foreground text-xs">{{ formatActivityTime(activity.created_at) }}</p>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">No activity recorded yet.</p>
            </div>
        </div>

        <div class="space-y-4">
            <form class="rounded-xl border bg-card p-5 space-y-3" @submit.prevent="saveLead">
                <h2 class="font-semibold">Manage Lead</h2>
                <div>
                    <label class="text-sm">Status</label>
                    <select v-model="updateForm.status" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">Assigned To</label>
                    <select v-model="updateForm.assigned_to" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option :value="null">Unassigned</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">Product</label>
                    <select v-model="updateForm.product" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option :value="null">None</option>
                        <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">Remarks</label>
                    <textarea v-model="updateForm.remarks" class="mt-1 w-full rounded-md border px-3 py-2 text-sm" />
                </div>
                <button type="submit" class="w-full rounded-md bg-primary py-2 text-sm text-primary-foreground">
                    Save Changes
                </button>
            </form>

            <form v-if="canInvite" class="rounded-xl border bg-card p-5 space-y-3" @submit.prevent="sendInvite">
                <h2 class="font-semibold">Send Onboarding Invite</h2>
                <select v-model="inviteForm.product" class="w-full rounded-md border px-3 py-2 text-sm">
                    <option v-for="p in products" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
                <button type="submit" class="w-full rounded-md bg-secondary py-2 text-sm text-white">
                    Send Invite
                </button>
            </form>

            <button
                v-if="canDelete"
                type="button"
                class="w-full rounded-md border border-destructive py-2 text-sm text-destructive"
                @click="deleteLead"
            >
                Delete Lead
            </button>
        </div>
    </div>
</template>
