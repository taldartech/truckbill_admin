<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, IndianRupee, LayoutGrid, Settings, Shield, Ticket, UserCircle, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import leads from '@/routes/leads';
import rolesRoute from '@/routes/roles';
import subscriptionsRoute from '@/routes/subscriptions';
import usersRoute from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const permissions = computed(() => (page.props.auth as { permissions?: string[] })?.permissions ?? []);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Leads', href: leads.index(), icon: Users },
    ];

    if (permissions.value.includes('users.manage')) {
        items.push({ title: 'Users', href: usersRoute.index(), icon: UserCircle });
    }

    if (permissions.value.includes('roles.manage')) {
        items.push({ title: 'Roles', href: rolesRoute.index(), icon: Shield });
    }

    return items;
});

const subscriptionNavItems = computed<NavItem[]>(() => {
    if (!permissions.value.includes('subscriptions.view')) {
        return [];
    }

    return [
        { title: 'Pricing', href: subscriptionsRoute.pricing(), icon: IndianRupee, matchPrefix: true },
        { title: 'Settings', href: '/subscriptions/settings', icon: Settings, matchPrefix: true },
        { title: 'Promo Codes', href: subscriptionsRoute.promoCodes(), icon: Ticket, matchPrefix: true },
        { title: 'Companies', href: subscriptionsRoute.companies(), icon: Building2, matchPrefix: true },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r border-sidebar-border shadow-sm">
        <SidebarHeader class="border-b border-sidebar-border">
            <AppLogo />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain v-if="subscriptionNavItems.length > 0" label="Subscriptions" :items="subscriptionNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border p-3">
            <p class="text-center text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                TruckBill CRM
            </p>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
