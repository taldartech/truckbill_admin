<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
</script>

<template>
    <Link :href="dashboard()" class="flex items-center justify-center px-2 py-3">
        <img
            src="/logo.png"
            alt="TruckBill"
            class="h-[72px] w-auto max-w-full object-contain"
        />
    </Link>
</template>
