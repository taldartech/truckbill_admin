<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Shield, UserCircle, Users } from '@lucide/vue';
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
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r border-sidebar-border shadow-sm">
        <SidebarHeader class="border-b border-sidebar-border">
            <AppLogo />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border p-3">
            <p class="text-center text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                TruckBill CRM
            </p>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
