<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
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
import { useAgentsNavSection } from '@/composables/useAgentsNavSection';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { agentsNavOpen } = useAgentsNavSection();

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
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <Collapsible
                    v-else-if="item.collapsibleVariant === 'agents'"
                    v-model:open="agentsNavOpen"
                    as-child
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="item.title">
                                <component v-if="item.icon" :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <svg
                                    v-if="agentsNavOpen"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    class="ml-auto size-4 shrink-0"
                                    aria-hidden="true"
                                >
                                    <title>chevron-down</title>
                                    <path
                                        d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    class="ml-auto size-4 shrink-0"
                                    aria-hidden="true"
                                >
                                    <title>chevron-up</title>
                                    <path
                                        d="M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z"
                                    />
                                </svg>
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
                                        :is-active="isCurrentUrl(child.href)"
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
                    :default-open="
                        item.children!.some((child) => isCurrentUrl(child.href))
                    "
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="item.title">
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
                                        :is-active="isCurrentUrl(child.href)"
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
