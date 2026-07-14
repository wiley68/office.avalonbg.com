<script setup lang="ts">
import { ChevronRight, Folder, FolderOpen } from 'lucide-vue-next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Button } from '@/components/ui/button';
import type { ImapFolderNode } from '@/components/projects/ImapFolderTree.vue';

type Props = {
    node: ImapFolderNode;
    depth: number;
    selectedFolder: string | null;
    isPathOpen: (path: string) => boolean;
};

defineProps<Props>();

const emit = defineEmits<{
    select: [folder: string];
    toggle: [path: string, open: boolean];
}>();
</script>

<template>
    <div class="space-y-0.5">
        <Collapsible
            v-if="node.children.length > 0"
            :open="isPathOpen(node.path)"
            class="space-y-0.5"
            @update:open="(open) => emit('toggle', node.path, open)"
        >
            <div class="flex items-center gap-0.5">
                <CollapsibleTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        :style="{ marginLeft: `${depth * 12}px` }"
                    >
                        <ChevronRight
                            class="size-4 transition-transform"
                            :class="{ 'rotate-90': isPathOpen(node.path) }"
                        />
                    </Button>
                </CollapsibleTrigger>
                <Button
                    variant="ghost"
                    class="h-8 min-w-0 flex-1 justify-start gap-2 px-2 font-normal"
                    :class="{ 'bg-muted': selectedFolder === node.path }"
                    @click="emit('select', node.path)"
                >
                    <FolderOpen
                        v-if="isPathOpen(node.path)"
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <Folder
                        v-else
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <span class="truncate text-sm">{{ node.name }}</span>
                </Button>
            </div>
            <CollapsibleContent class="space-y-0.5">
                <ImapFolderTreeBranch
                    v-for="child in node.children"
                    :key="child.path"
                    :node="child"
                    :depth="depth + 1"
                    :selected-folder="selectedFolder"
                    :is-path-open="isPathOpen"
                    @select="emit('select', $event)"
                    @toggle="(path, open) => emit('toggle', path, open)"
                />
            </CollapsibleContent>
        </Collapsible>

        <Button
            v-else
            variant="ghost"
            class="h-8 w-full justify-start gap-2 px-2 font-normal"
            :class="{ 'bg-muted': selectedFolder === node.path }"
            :style="{ paddingLeft: `${depth * 12 + 8}px` }"
            @click="emit('select', node.path)"
        >
            <Folder class="size-4 shrink-0 text-muted-foreground" />
            <span class="truncate text-sm">{{ node.name }}</span>
        </Button>
    </div>
</template>
