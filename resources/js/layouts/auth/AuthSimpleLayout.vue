<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="crm-auth-shell">
        <div class="crm-auth-card">
            <div class="mb-8 flex flex-col items-center gap-4 text-center">
                <Link :href="home()" class="inline-flex">
                    <img
                        src="/logo.png"
                        alt="TruckBill"
                        class="h-24 w-auto max-w-full object-contain"
                    />
                </Link>
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>
