<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, StickyNote, Users } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    NavigationMenuItem,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { useAgentsNavSection } from '@/composables/useAgentsNavSection';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';

type Props = {
    variant: 'sheet' | 'bar';
};

defineProps<Props>();

const { agentsNavOpen } = useAgentsNavSection();
const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

const notesUrl = dashboardRoutes.notes.url();
</script>

<template>
    <template v-if="variant === 'sheet'">
        <nav class="-mx-3 space-y-1">
            <Link
                :href="dashboard()"
                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                :class="whenCurrentUrl(dashboard(), activeItemStyles)"
            >
                <LayoutGrid class="h-5 w-5" />
                Композитор
            </Link>
            <Collapsible v-model:open="agentsNavOpen">
                <CollapsibleTrigger
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-accent"
                >
                    <span class="flex items-center gap-x-3">
                        <Users class="h-5 w-5" />
                        Агенти
                    </span>
                    <svg
                        v-if="agentsNavOpen"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        class="size-4 shrink-0"
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
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    >
                        <title>chevron-up</title>
                        <path
                            d="M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z"
                        />
                    </svg>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <Link
                        :href="notesUrl"
                        class="flex items-center gap-x-3 rounded-lg py-2 pr-3 pl-10 text-sm font-medium hover:bg-accent"
                        :class="whenCurrentUrl(notesUrl, activeItemStyles)"
                    >
                        <StickyNote class="h-5 w-5" />
                        Бележки
                    </Link>
                </CollapsibleContent>
            </Collapsible>
        </nav>
    </template>

    <template v-else>
        <NavigationMenuItem class="relative flex h-full items-center">
            <Link
                :class="[
                    navigationMenuTriggerStyle(),
                    whenCurrentUrl(dashboard(), activeItemStyles),
                    'h-9 cursor-pointer px-3',
                ]"
                :href="dashboard()"
            >
                <LayoutGrid class="mr-2 h-4 w-4" />
                Композитор
            </Link>
            <div
                v-if="isCurrentUrl(dashboard())"
                class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
            />
        </NavigationMenuItem>
        <NavigationMenuItem class="relative flex h-full items-center">
            <Collapsible v-model:open="agentsNavOpen">
                <CollapsibleTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        :class="[
                            navigationMenuTriggerStyle(),
                            whenCurrentUrl(notesUrl, activeItemStyles),
                            'h-9 cursor-pointer gap-2 px-3 font-medium',
                        ]"
                    >
                        <Users class="h-4 w-4" />
                        Агенти
                        <svg
                            v-if="agentsNavOpen"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="size-4 shrink-0"
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
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        >
                            <title>chevron-up</title>
                            <path
                                d="M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z"
                            />
                        </svg>
                    </Button>
                </CollapsibleTrigger>
                <CollapsibleContent
                    class="absolute top-full left-0 z-50 mt-1 min-w-48 rounded-md border border-border bg-popover p-1 shadow-md"
                >
                    <Link
                        :href="notesUrl"
                        class="flex items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-accent"
                        :class="whenCurrentUrl(notesUrl, activeItemStyles)"
                    >
                        <StickyNote class="h-4 w-4" />
                        Бележки
                    </Link>
                </CollapsibleContent>
            </Collapsible>
        </NavigationMenuItem>
    </template>
</template>
