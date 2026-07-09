<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppToast } from '@/composables/useAppToast';
import { useManageableUsersLabels } from '@/composables/useManageableUsersLabels';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePasswordHint } from '@/lib/passwordHint';
import { dashboard } from '@/routes';
import { edit, index, update } from '@/routes/users';
import type { BreadcrumbItem, UserRole } from '@/types';

const UserTwoFactorPanel = defineAsyncComponent(
    () => import('@/components/UserTwoFactorPanel.vue'),
);

type EditableUser = {
    id: number;
    name: string;
    email: string;
    two_factor_enabled?: boolean;
};

const props = defineProps<{
    user: EditableUser;
    manageableRole?: UserRole;
}>();

const { t } = useTranslations();
const labels = useManageableUsersLabels(() => props.manageableRole);
const passwordHint = usePasswordHint();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
});

const isNavigating = ref(false);
const tab = ref('profile');

const { showMessage, showError } = useAppToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
    },
    {
        title: labels.value.plural,
        href: index(),
    },
    {
        title: labels.value.editTitle,
        href: edit(props.user.id),
    },
]);

const handleCancel = () => {
    isNavigating.value = true;
    router.get(index().url, {}, {
        onFinish: () => {
            isNavigating.value = false;
        },
    });
};

const onSubmit = () => {
    const { password, password_confirmation, ...baseData } = form.data();
    const formData = {
        ...baseData,
        ...(password ? { password, password_confirmation } : {}),
    };

    form.transform(() => formData).put(update(props.user.id).url, {
        onSuccess: () => {
            showMessage(t('common.success'), t('users.updated_success'));
        },
        onError: (errors) => {
            const all = Object.values(errors).flat().filter(Boolean).join('\n');
            showError(t('common.error'), all || t('common.form_errors'));
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="labels.editTitle" />

        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ labels.editTitle }}</h1>
                    <p class="text-muted-foreground">{{ user.name }}</p>
                </div>
            </div>

            <Card class="mx-auto w-full max-w-2xl">
                <CardHeader>
                    <CardTitle>{{ t('users.card_title') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Tabs v-model="tab" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="profile" class="cursor-pointer">
                                {{ t('users.tabs.profile') }}
                            </TabsTrigger>
                            <TabsTrigger value="twofactor" class="cursor-pointer">
                                {{ t('users.tabs.two_factor') }}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="profile">
                            <form class="space-y-4" @submit.prevent="onSubmit">
                                <div class="space-y-2">
                                    <Label for="name">{{ t('users.fields.user') }} *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        :placeholder="t('users.fields.user_placeholder')"
                                        :class="{
                                            'border-destructive': form.errors.name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="email">{{ t('common.email') }} *</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        :placeholder="t('users.fields.email_placeholder')"
                                        :class="{
                                            'border-destructive': form.errors.email,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.email"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.email }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="password">{{ t('password.new') }}</Label>
                                    <TooltipProvider :delay-duration="200">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Input
                                                    id="password"
                                                    v-model="form.password"
                                                    type="password"
                                                    :placeholder="t('password.leave_blank')"
                                                    :class="{
                                                        'border-destructive':
                                                            form.errors.password,
                                                    }"
                                                />
                                            </TooltipTrigger>
                                            <TooltipContent side="top" class="max-w-xs">
                                                <p>{{ passwordHint }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <p
                                        v-if="form.errors.password"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="password_confirmation">
                                        {{ t('password.repeat_new') }}
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        :placeholder="t('password.repeat_new_placeholder')"
                                        :class="{
                                            'border-destructive':
                                                form.errors.password_confirmation,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.password_confirmation"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.password_confirmation }}
                                    </p>
                                </div>

                                <div class="flex justify-end gap-2 pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="isNavigating"
                                        class="cursor-pointer"
                                        @click="handleCancel"
                                    >
                                        <Icon
                                            v-if="isNavigating"
                                            name="Loader2"
                                            class="mr-2 h-4 w-4 animate-spin"
                                        />
                                        <Icon
                                            v-else
                                            name="ArrowLeft"
                                            class="mr-2 h-4 w-4"
                                        />
                                        {{ t('common.cancel') }}
                                    </Button>
                                    <Button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="cursor-pointer"
                                    >
                                        <Icon
                                            v-if="form.processing"
                                            name="Loader2"
                                            class="mr-2 h-4 w-4 animate-spin"
                                        />
                                        <Icon
                                            v-else
                                            name="Save"
                                            class="mr-2 h-4 w-4"
                                        />
                                        {{
                                            form.processing
                                                ? t('common.saving')
                                                : t('common.update')
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </TabsContent>

                        <TabsContent value="twofactor" class="space-y-4">
                            <UserTwoFactorPanel
                                :user-id="user.id"
                                :user-email="user.email"
                                :two-factor-enabled="user.two_factor_enabled ?? false"
                            />
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
