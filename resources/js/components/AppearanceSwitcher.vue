<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { useTranslations } from '@/composables/useTranslations';
import type { Appearance } from '@/types';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTranslations();

const options: Array<{
    value: Appearance;
    Icon: typeof Sun;
    labelKey: string;
}> = [
    { value: 'light', Icon: Sun, labelKey: 'appearance.light' },
    { value: 'dark', Icon: Moon, labelKey: 'appearance.dark' },
    { value: 'system', Icon: Monitor, labelKey: 'appearance.system' },
];
</script>

<template>
    <div
        role="group"
        :aria-label="t('appearance.label')"
        class="inline-flex overflow-hidden rounded-sm border border-[#19140035] dark:border-[#3E3E3A]"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-label="t(option.labelKey)"
            :aria-pressed="appearance === option.value"
            :title="t(option.labelKey)"
            class="inline-flex items-center justify-center px-2.5 py-1.5 text-[#1b1b18] transition-colors not-first:border-l not-first:border-[#19140035] hover:bg-[#19140012] dark:text-[#EDEDEC] dark:not-first:border-[#3E3E3A] dark:hover:bg-[#ffffff12]"
            :class="
                appearance === option.value
                    ? 'bg-[#19140012] dark:bg-[#ffffff12]'
                    : ''
            "
            @click="updateAppearance(option.value)"
        >
            <component :is="option.Icon" class="h-4 w-4" />
        </button>
    </div>
</template>
