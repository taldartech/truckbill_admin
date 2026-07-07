<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Inbox, Sparkles, Target, TrendingUp, Users } from '@lucide/vue';
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
        converted: number;
        lost: number;
        by_product: Record<string, number>;
        by_source: Record<string, number>;
    };
}>();

const { can } = usePermissions();

const cards = [
    { key: 'new', label: 'New Leads', icon: Inbox, tone: 'primary' as const },
    { key: 'qualified', label: 'Qualified', icon: Target, tone: 'blue' as const },
    { key: 'converted', label: 'Converted', icon: TrendingUp, tone: 'success' as const },
    { key: 'lost', label: 'Lost', icon: Sparkles, tone: 'muted' as const },
] as const;
</script>

<template>
    <Head title="CRM Dashboard" />

    <div class="crm-page">
        <CrmPageHeader
            title="CRM Dashboard"
            description="Lead pipeline overview and conversion metrics"
        >
            <template #actions>
                <Button v-if="can('leads.view')" as-child class="rounded-xl">
                    <Link :href="leads.index()">View Leads</Link>
                </Button>
            </template>
        </CrmPageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <CrmStatCard
                v-for="card in cards"
                :key="card.key"
                :label="card.label"
                :value="stats[card.key]"
                :icon="card.icon"
                :tone="card.tone"
            />
        </div>

        <CrmStatCard
            label="Total Leads"
            :value="stats.total"
            :icon="Users"
            tone="primary"
            class="max-w-sm"
        />

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
