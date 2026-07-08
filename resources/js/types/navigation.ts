import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavSubItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Match a specific status query value. */
    status?: string;
    /** Match a specific follow_up query value. */
    follow_up?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Highlight when current URL starts with this item's path (for nested pages). */
    matchPrefix?: boolean;
    children?: NavSubItem[];
};
