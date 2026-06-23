<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{
    title?: string;
    flush?: boolean;
    class?: string;
}>();
</script>

<template>
    <section :class="cn('crm-panel', $props.class)">
        <header v-if="title || $slots.header" class="crm-panel-header">
            <slot name="header">
                <h2 v-if="title" class="text-base font-semibold">{{ title }}</h2>
            </slot>
        </header>
        <div :class="cn('crm-panel-body', flush && 'p-0')">
            <slot />
        </div>
    </section>
</template>
