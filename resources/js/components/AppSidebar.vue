<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    ShoppingCart,
    LayoutGrid,
    StickyNote,
    Table,
    Users,
} from 'lucide-vue-next';
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
import dashboardRoutes from '@/routes/dashboard';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    if (page.props.auth.user?.is_admin) {
        return [
            {
                title: 'Потребители',
                href: usersIndex(),
                icon: Users,
            },
            {
                title: 'Статистика',
                href: dashboardRoutes.admin.statistics.url(),
                icon: BarChart3,
            },
            {
                title: 'Експорт',
                href: dashboardRoutes.admin.export.url(),
                icon: Table,
            },
        ];
    }

    return [
        {
            title: 'Композитор',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Бележки',
            href: dashboardRoutes.notes.url(),
            icon: StickyNote,
        },
    ];
});

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: 'Магазин',
        href: page.props.shopUrl,
        target: '_blank',
        rel: 'noopener noreferrer',
        icon: ShoppingCart,
    },
]);
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
