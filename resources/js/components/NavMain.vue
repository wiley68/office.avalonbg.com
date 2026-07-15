<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Ref } from 'vue';
import NavCollapsibleChevron from '@/components/NavCollapsibleChevron.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { useUsersNavSection } from '@/composables/useUsersNavSection';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();

function isNavItemActive(item: NavItem): boolean {
    if (item.href && isCurrentOrParentUrl(item.href)) {
        return true;
    }

    return (
        item.children?.some((child) => isCurrentOrParentUrl(child.href)) ??
        false
    );
}
const { usersNavOpen } = useUsersNavSection();

const toggleSectionOpen: Record<'users', Ref<boolean>> = {
    users: usersNavOpen,
};

function isToggleCollapsible(
    variant: NavItem['collapsibleVariant'],
): variant is 'users' {
    return variant === 'users';
}

function isToggleOpen(variant: 'users'): boolean {
    return toggleSectionOpen[variant].value;
}

function setToggleOpen(variant: 'users', open: boolean): void {
    toggleSectionOpen[variant].value = open;
}

function toggleOpenForItem(variant: NavItem['collapsibleVariant']): boolean {
    return isToggleCollapsible(variant) ? isToggleOpen(variant) : false;
}

function onToggleOpenUpdate(
    variant: NavItem['collapsibleVariant'],
    open: boolean,
): void {
    if (isToggleCollapsible(variant)) {
        setToggleOpen(variant, open);
    }
}

const page = usePage();

const organizationLabel = computed(() => {
    const o = page.props.organization;

    return typeof o === 'string' && o.trim() !== '' ? o : 'Maxtrade AI Office';
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ organizationLabel }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <div
                    v-if="item.separator"
                    class="px-2 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-muted-foreground/80 uppercase"
                >
                    {{ item.title }}
                </div>
                <SidebarMenuItem
                    v-else-if="!item.children || item.children.length === 0"
                >
                    <SidebarMenuButton
                        as-child
                        :is-active="isNavItemActive(item)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <Collapsible
                    v-else-if="isToggleCollapsible(item.collapsibleVariant)"
                    :open="toggleOpenForItem(item.collapsibleVariant)"
                    as-child
                    @update:open="onToggleOpenUpdate(item.collapsibleVariant, $event)"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isNavItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component v-if="item.icon" :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <NavCollapsibleChevron
                                    :open="isToggleOpen(item.collapsibleVariant)"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isCurrentOrParentUrl(child.href)"
                                    >
                                        <Link :href="child.href">
                                            <component
                                                v-if="child.icon"
                                                :is="child.icon"
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
                <Collapsible
                    v-else
                    as-child
                    :default-open="isNavItemActive(item)"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isNavItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isCurrentOrParentUrl(child.href)"
                                    >
                                        <Link :href="child.href">
                                            <component
                                                v-if="child.icon"
                                                :is="child.icon"
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
