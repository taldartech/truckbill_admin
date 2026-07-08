<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { reactive, watch } from 'vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem, NavSubItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const manualOpen = reactive<Record<string, boolean>>({});

function isActive(item: NavItem): boolean {
    if (item.matchPrefix) {
        return isCurrentOrParentUrl(item.href);
    }

    return isCurrentUrl(item.href);
}

function isSubItemActive(item: NavSubItem): boolean {
    const current = new URL(page.url, 'http://localhost');
    const target = new URL(toUrl(item.href), 'http://localhost');

    if (current.pathname !== target.pathname) {
        return false;
    }

    const currentStatus = current.searchParams.get('status') ?? '';

    if (item.status === undefined) {
        return currentStatus === '';
    }

    return currentStatus === item.status;
}

function isItemOpen(item: NavItem): boolean {
    if (item.title in manualOpen) {
        return manualOpen[item.title];
    }

    return isActive(item);
}

function setItemOpen(item: NavItem, open: boolean): void {
    manualOpen[item.title] = open;
}

watch(
    () => page.url,
    () => {
        for (const item of props.items) {
            if (item.children?.length && !isActive(item)) {
                delete manualOpen[item.title];
            }
        }
    },
);
</script>

<template>
    <SidebarGroup class="px-2 py-2">
        <SidebarGroupLabel class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {{ label ?? 'Main Menu' }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <Collapsible
                    v-if="item.children?.length"
                    :open="isItemOpen(item)"
                    class="group/collapsible"
                    @update:open="(open) => setItemOpen(item, open)"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isActive(item)"
                                :tooltip="item.title"
                                class="h-10 rounded-none px-3"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="child in item.children" :key="child.title">
                                    <SidebarMenuSubButton as-child :is-active="isSubItemActive(child)">
                                        <Link :href="child.href">
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                        class="h-10 rounded-none px-3"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
