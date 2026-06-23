<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    label: string;
    value: string | number;
    icon?: Component;
    tone?: 'primary' | 'blue' | 'success' | 'muted';
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('crm-stat-card', props.class)">
        <div
            v-if="icon"
            class="crm-stat-icon"
            :class="{
                'crm-stat-icon--primary': tone === 'primary' || !tone,
                'crm-stat-icon--blue': tone === 'blue',
                'crm-stat-icon--success': tone === 'success',
                'crm-stat-icon--muted': tone === 'muted',
            }"
        >
            <component :is="icon" class="size-5" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight">{{ value }}</p>
        </div>
    </div>
</template>
