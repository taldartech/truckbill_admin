<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Calendar, CalendarClock, Hourglass, Inbox, TrendingUp, Users } from '@lucide/vue';
import { computed } from 'vue';
import CrmPageHeader from '@/components/crm/CrmPageHeader.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import CrmStatCard from '@/components/crm/CrmStatCard.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import leads from '@/routes/leads';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
        ],
    },
});

defineProps<{
    stats: {
        total: number;
        new: number;
        contacted: number;
        qualified: number;
        demo_scheduled: number;
        proposal_sent: number;
        trial: number;
        converted: number;
        lost: number;
        follow_up: {
            overdue: number;
            due_today: number;
            upcoming: number;
        };
        by_product: Record<string, number>;
        by_source: Record<string, number>;
    };
}>();

const { can } = usePermissions();
const page = usePage();
const userId = computed(() => (page.props.auth as { user?: { id: number } }).user?.id);

const statusCards = [
    { key: 'new', label: 'New Leads', icon: Inbox, tone: 'primary' as const },
    { key: 'trial', label: 'Trial', icon: Hourglass, tone: 'blue' as const },
    { key: 'converted', label: 'Converted', icon: TrendingUp, tone: 'success' as const },
    { key: 'total', label: 'Total Leads', icon: Users, tone: 'primary' as const },
] as const;

const followUpCards = [
    { key: 'overdue', label: 'Overdue', icon: AlertTriangle, tone: 'muted' as const },
    { key: 'due_today', label: 'Due today', icon: CalendarClock, tone: 'blue' as const },
    { key: 'upcoming', label: 'Upcoming', icon: Calendar, tone: 'primary' as const },
] as const;

function followUpHref(filter: string) {
    return leads.index.url({
        query: {
            follow_up: filter,
            assigned_to: userId.value ? String(userId.value) : undefined,
        },
    });
}
</script>

<template>
    <Head title="CRM Dashboard" />

    <div class="crm-page">
        <CrmPageHeader
            title="CRM Dashboard"
            description="Lead pipeline overview and your assigned follow-ups"
        >
            <template #actions>
                <Button v-if="can('leads.view')" as-child class="rounded-xl">
                    <Link :href="leads.index()">View Leads</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <CrmStatCard
                v-for="card in statusCards"
                :key="card.key"
                :label="card.label"
                :value="stats[card.key]"
                :icon="card.icon"
                :tone="card.tone"
            />
            <Link
                v-for="card in followUpCards"
                :key="card.key"
                :href="followUpHref(card.key)"
                class="block rounded-2xl transition-opacity hover:opacity-90"
            >
                <CrmStatCard
                    :label="card.label"
                    :value="stats.follow_up[card.key]"
                    :icon="card.icon"
                    :tone="card.tone"
                />
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <CrmPanel title="By Product">
                <ul class="space-y-3 text-sm">
                    <li
                        v-for="(count, product) in stats.by_product"
                        :key="product"
                        class="flex items-center justify-between border-b border-border/70 pb-2 last:border-0 last:pb-0"
                    >
                        <span class="font-medium capitalize">{{ product }}</span>
                        <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-accent-foreground">
                            {{ count }}
                        </span>
                    </li>
                    <li v-if="Object.keys(stats.by_product).length === 0" class="text-muted-foreground">
                        No product data yet
                    </li>
                </ul>
            </CrmPanel>

            <CrmPanel title="By Source">
                <ul class="space-y-3 text-sm">
                    <li
                        v-for="(count, source) in stats.by_source"
                        :key="source"
                        class="flex items-center justify-between border-b border-border/70 pb-2 last:border-0 last:pb-0"
                    >
                        <span class="font-medium">{{ source }}</span>
                        <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-foreground">
                            {{ count }}
                        </span>
                    </li>
                </ul>
            </CrmPanel>
        </div>
    </div>
</template>
