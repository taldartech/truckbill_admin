<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Onboarding link expired',
        description: 'This onboarding link is no longer valid',
    },
});
</script>

<template>
    <Head title="Onboarding link expired" />

    <p class="mb-6 text-sm text-muted-foreground">
        This onboarding link is invalid or has expired. Please contact your TruckBill administrator to request a new invitation.
    </p>

    <Button as-child class="w-full">
        <Link :href="login()">Go to login</Link>
    </Button>
</template>
