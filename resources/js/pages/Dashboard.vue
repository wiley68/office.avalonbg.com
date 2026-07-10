<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
const { t } = useTranslations();

const isProfiler = computed(
    () => page.props.auth.user?.is_profiler === true,
);

const adminUserCount = computed(
    () => Number(page.props.admin_user_count ?? 0),
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
    },
]);
</script>

<template>
    <Head :title="t('common.dashboard')" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
    >
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div
                v-if="isProfiler"
                class="grid auto-rows-min gap-4 md:grid-cols-1"
            >
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <Card class="h-full">
                        <CardContent class="space-y-2">
                            <Link
                                :href="usersIndex()"
                                class="flex items-center justify-between rounded-lg bg-muted/50 p-3 hover:bg-muted/70"
                            >
                                <div class="flex items-center gap-3">
                                    <Shield class="h-5 w-5 text-blue-600" />
                                    <div>
                                        <p class="font-medium">
                                            {{ t('users.admin.plural') }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            {{ t('dashboard.administrators_subtitle') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-blue-600">
                                        {{ adminUserCount }}
                                    </p>
                                </div>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
