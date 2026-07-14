<script setup lang="ts">
import { ref } from 'vue';
import ImapFolderTreeBranch from '@/components/projects/ImapFolderTreeBranch.vue';

export type ImapFolderNode = {
    name: string;
    path: string;
    children: ImapFolderNode[];
};

type Props = {
    folders: ImapFolderNode[];
    selectedFolder: string | null;
    loading?: boolean;
};

defineProps<Props>();

const emit = defineEmits<{
    select: [folder: string];
}>();

const openPaths = ref<Set<string>>(new Set());

const togglePath = (path: string, open: boolean): void => {
    if (open) {
        openPaths.value.add(path);
    } else {
        openPaths.value.delete(path);
    }
};

const selectFolder = (path: string): void => {
    const parts = path.split('.');
    let accumulated = '';

    for (const part of parts) {
        accumulated = accumulated === '' ? part : `${accumulated}.${part}`;
        openPaths.value.add(accumulated);
    }

    emit('select', path);
};

const isPathOpen = (path: string): boolean => openPaths.value.has(path);
</script>

<template>
    <div class="space-y-0.5">
        <div
            v-if="loading"
            class="px-2 py-4 text-xs text-muted-foreground"
        >
            ...
        </div>

        <div
            v-else-if="folders.length === 0"
            class="px-2 py-4 text-xs text-muted-foreground"
        >
            —
        </div>

        <ImapFolderTreeBranch
            v-for="folder in folders"
            v-else
            :key="folder.path"
            :node="folder"
            :depth="0"
            :selected-folder="selectedFolder"
            :is-path-open="isPathOpen"
            @select="selectFolder"
            @toggle="togglePath"
        />
    </div>
</template>
