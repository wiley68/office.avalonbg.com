<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import {
    FolderKanban,
    HardDriveDownload,
    History,
    KeyRound,
    LayoutGrid,
    Mail,
    ScrollText,
    User,
    Users,
    FileText,
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
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import { index as accessesIndex } from '@/routes/accesses';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as documentsIndex } from '@/routes/documents';
import { index as projectsIndex } from '@/routes/projects';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const { t } = useTranslations();

const usersNavItem = (user: NonNullable<typeof page.props.auth.user>): NavItem | null => {
    if (!user.can_manage_users) {
        return null;
    }

    const childTitle = user.is_profiler
        ? t('users.admin.plural')
        : t('users.office_user.plural');

    return {
        title: t('nav.users'),
        href: '',
        icon: Users,
        collapsibleVariant: 'users',
        children: [
            {
                title: childTitle,
                href: usersIndex(),
                icon: User,
            },
        ],
    };
};

const mainNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user) {
        return [];
    }

    const usersItem = usersNavItem(user);

    if (user.is_profiler) {
        return [
            {
                title: t('common.dashboard'),
                href: dashboard(),
                icon: LayoutGrid,
            },
            ...(usersItem ? [usersItem] : []),
            {
                title: t('nav.logs'),
                href: '',
                icon: ScrollText,
                children: [
                    {
                        title: t('nav.audit'),
                        href: auditLogsIndex(),
                        icon: History,
                    },
                ],
            },
        ];
    }

    if (user.has_office_access) {
        const items: NavItem[] = [
            {
                title: t('common.dashboard'),
                href: dashboard(),
                icon: LayoutGrid,
            },
            ...(usersItem ? [usersItem] : []),
        ];

        if (user.is_office_user) {
            items.push({
                title: t('nav.projects'),
                href: projectsIndex(),
                icon: FolderKanban,
            });
            items.push({
                title: t('nav.documents'),
                href: documentsIndex(),
                icon: FileText,
            });
            items.push({
                title: t('nav.accesses'),
                href: accessesIndex(),
                icon: KeyRound,
            });
        }

        return items;
    }

    return [];
});

const footerNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user) {
        return [];
    }

    return [
        {
            title: user.role_label ?? t('roles.user'),
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
