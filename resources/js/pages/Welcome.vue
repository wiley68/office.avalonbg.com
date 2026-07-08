<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppearanceSwitcher from '@/components/AppearanceSwitcher.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard, login } from '@/routes';

const page = usePage();
const { t } = useTranslations();
</script>

<template>
    <Head :title="$page.props.name">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]"
    >
        <header
            class="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl"
        >
            <nav class="flex items-center justify-end gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                >
                    {{ t('welcome.dashboard') }}
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        {{ t('welcome.sign_in') }}
                    </Link>
                </template>
                <LocaleSwitcher />
                <AppearanceSwitcher />
            </nav>
        </header>
        <div class="flex w-full items-center justify-center lg:grow">
            <main
                class="flex w-full max-w-[335px] flex-col-reverse overflow-hidden rounded-lg lg:max-w-4xl lg:flex-row"
            >
                <div
                    class="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
                >
                    <h1 class="mb-1 font-medium">
                        {{ t('welcome.title') }}
                    </h1>
                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">
                        {{
                            t('welcome.description', {
                                name: String(page.props.name),
                            })
                        }}
                        <TextLink :href="`mailto:${page.props.email}`">{{
                            page.props.email
                        }}</TextLink
                        >.
                    </p>
                    <Separator />
                    <ul class="mt-3 flex gap-3 text-sm leading-normal">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                        >
                            {{ t('welcome.dashboard') }}
                        </Link>
                        <template v-else>
                            <Button
                                class="cursor-pointer"
                                @click="router.get(login())"
                            >
                                {{ t('welcome.sign_in') }}
                            </Button>
                        </template>
                    </ul>
                </div>
                <div
                    class="relative -mb-px flex w-full shrink-0 items-center justify-center overflow-hidden rounded-t-lg bg-[#f5f5f4] p-8 lg:mb-0 lg:-ml-px lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg dark:bg-[#262625]"
                >
                    <img
                        src="/images/logo.png"
                        alt="Logo"
                        width="237"
                        height="62"
                        class="block h-auto w-auto max-w-none shrink-0"
                    />
                    <div
                        class="pointer-events-none absolute inset-0 rounded-t-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:overflow-hidden lg:rounded-t-none lg:rounded-r-lg dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
                    />
                </div>
            </main>
        </div>
        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
