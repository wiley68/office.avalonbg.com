<script setup lang="ts">
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type TableRowAction = {
    label: string;
    icon?: Component;
    onSelect: () => void;
    variant?: 'default' | 'destructive';
};

withDefaults(
    defineProps<{
        actions: TableRowAction[];
        label?: string;
    }>(),
    {
        label: 'Управление',
    },
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                class="h-8 w-8 p-0 text-base leading-none"
                type="button"
                aria-label="Управление"
            >
                ...
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuLabel>{{ label }}</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="action in actions"
                :key="action.label"
                :variant="action.variant"
                @click="action.onSelect"
            >
                <component
                    :is="action.icon"
                    v-if="action.icon"
                    class="mr-2 h-4 w-4"
                />
                {{ action.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
