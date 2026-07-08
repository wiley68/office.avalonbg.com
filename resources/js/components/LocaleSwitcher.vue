<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { locale, locales } = useTranslations();

const switchLocale = (event: Event): void => {
    const target = event.target as HTMLSelectElement;
    const nextLocale = target.value;

    if (nextLocale === locale.value) {
        return;
    }

    router.get(
        `/locale/${nextLocale}`,
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <label class="sr-only" for="locale-switcher">Language</label>
    <select
        id="locale-switcher"
        :value="locale"
        class="inline-block rounded-sm border border-[#19140035] bg-transparent px-3 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
        @change="switchLocale"
    >
        <option
            v-for="option in locales"
            :key="option.code"
            :value="option.code"
        >
            {{ option.label }}
        </option>
    </select>
</template>
