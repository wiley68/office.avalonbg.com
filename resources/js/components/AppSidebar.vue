<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bot,
    HardDriveDownload,
    History,
    LayoutGrid,
    Mail,
    ScrollText,
    StickyNote,
    Table,
    User,
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
import { index as auditLogsIndex } from '@/routes/audit-logs';
import dashboardRoutes from '@/routes/dashboard';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user) {
        return [];
    }

    if (user.is_profiler) {
        return [
            {
                title: 'Табло',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Администратори',
                href: usersIndex(),
                icon: Users,
            },
            {
                title: 'Журнали',
                href: '',
                icon: ScrollText,
                children: [
                    {
                        title: 'Одит',
                        href: auditLogsIndex(),
                        icon: History,
                    },
                ],
            },
        ];
    }

    const items: NavItem[] = [
        {
            title: 'Табло',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Композитор',
            href: dashboardRoutes.composer.url(),
            icon: Bot,
        },
        {
            title: 'Агенти',
            href: dashboard(),
            separator: true,
        },
        {
            title: 'Бележки',
            href: dashboardRoutes.notes.url(),
            icon: StickyNote,
        },
    ];

    if (user.can_manage_users) {
        items.unshift({
            title: 'Потребители',
            href: usersIndex(),
            icon: Users,
        });
    }

    if (user.has_office_access) {
        items.push(
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
        );
    }

    return items;
});

const footerNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user) {
        return [];
    }

    return [
        {
            title: user.role_label ?? 'Потребител',
            href: '',
            icon: User,
        },
        {
            title: user.email,
            href: '',
            icon: Mail,
        },
        {
            title: String(page.props.version),
            href: '',
            icon: HardDriveDownload,
        },
    ];
});
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
