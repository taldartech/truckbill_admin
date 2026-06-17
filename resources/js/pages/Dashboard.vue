<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const cards = [
    { key: 'new', label: 'New Leads', color: 'bg-emerald-500' },
    { key: 'qualified', label: 'Qualified', color: 'bg-blue-500' },
    { key: 'converted', label: 'Converted', color: 'bg-green-600' },
    { key: 'lost', label: 'Lost', color: 'bg-gray-500' },
] as const;
</script>

<template>
    <Head title="CRM Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">CRM Dashboard</h1>
                <p class="text-muted-foreground text-sm">Lead pipeline overview</p>
            </div>
            <Link
                :href="leads.index()"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
            >
                View Leads
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.key"
                class="rounded-xl border bg-card p-5 shadow-sm"
            >
                <p class="text-muted-foreground text-sm">{{ card.label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ stats[card.key] }}</p>
            </div>
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <p class="text-muted-foreground text-sm">Total Leads</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.total }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-4 font-semibold">By Product</h2>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(count, product) in stats.by_product"
                        :key="product"
                        class="flex justify-between"
                    >
                        <span class="capitalize">{{ product }}</span>
                        <span class="font-medium">{{ count }}</span>
                    </li>
                    <li v-if="Object.keys(stats.by_product).length === 0" class="text-muted-foreground">
                        No product data yet
                    </li>
                </ul>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-4 font-semibold">By Source</h2>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(count, source) in stats.by_source"
                        :key="source"
                        class="flex justify-between"
                    >
                        <span>{{ source }}</span>
                        <span class="font-medium">{{ count }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
