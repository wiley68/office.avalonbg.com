import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    separator?: boolean;
    isActive?: boolean;
    children?: Array<{
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        icon?: LucideIcon;
    }>;
    /** Специален toggle „Агенти“ с chevron-up/down вместо стандартната стрелка */
    collapsibleVariant?: 'default' | 'agents';
};
