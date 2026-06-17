<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Shield, UserCircle, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
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

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: '/docs/architecture.md',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
