<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { generateRandomPassword } from '@/lib/generateRandomPassword';
import { usePasswordHint } from '@/lib/passwordHint';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/users';
import type { BreadcrumbItem, UserRole } from '@/types';

const props = defineProps<{
    manageableRole?: UserRole;
}>();

const { t } = useTranslations();
const labels = useManageableUsersLabels(() => props.manageableRole);
const passwordHint = usePasswordHint();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isNavigating = ref(false);
const tab = ref('profile');
const showGeneratedPasswordDialog = ref(false);
const passwordCopied = ref(false);

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
        title: labels.value.createTitle,
        href: create(),
    },
]);

const generatedPasswordUserLabel = computed(() => {
    if (form.name && form.email) {
        return t('password.generated_description_user', {
            name: form.name,
            email: form.email,
        });
    }

    if (form.name) {
        return t('password.generated_description_name', { name: form.name });
    }

    if (form.email) {
        return t('password.generated_description_email', { email: form.email });
    }

    return labels.value.singular.toLowerCase();
});

const generatedPasswordDescription = computed(() =>
    t('password.generated_description', {
        user: generatedPasswordUserLabel.value,
    }),
);

const handleCancel = () => {
    isNavigating.value = true;
    router.get(index().url, {}, {
        onFinish: () => {
            isNavigating.value = false;
        },
    });
};

const onSubmit = () => {
    form.post(store().url, {
        onError: (errors) => {
            const all = Object.values(errors).flat().filter(Boolean).join('\n');
            showError(t('common.error'), all || t('common.form_errors'));
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};

function fillGeneratedPassword(): void {
    const password = generateRandomPassword();

    form.password = password;
    form.password_confirmation = password;
    form.clearErrors('password', 'password_confirmation');
    showGeneratedPasswordDialog.value = true;
    passwordCopied.value = false;
}

function closeGeneratedPasswordDialog(): void {
    showGeneratedPasswordDialog.value = false;
    passwordCopied.value = false;
}

async function copyGeneratedPassword(): Promise<void> {
    if (!form.password) {
        return;
    }

    try {
        await navigator.clipboard.writeText(form.password);
        passwordCopied.value = true;
        showMessage(t('common.success'), t('password.copy_success'));
    } catch {
        showError(t('common.error'), t('password.copy_error'));
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="labels.createTitle" />

        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ labels.createTitle }}</h1>
                    <p class="text-muted-foreground">
                        {{ t('users.create_subtitle', { role: labels.singular.toLowerCase() }) }}
                    </p>
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
                                        autofocus
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
                                    <Label for="password">{{ t('password.label') }} *</Label>
                                    <div class="flex items-center gap-2">
                                        <TooltipProvider :delay-duration="200">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Input
                                                        id="password"
                                                        v-model="form.password"
                                                        type="password"
                                                        :placeholder="t('password.placeholder')"
                                                        :class="{
                                                            'border-destructive':
                                                                form.errors.password,
                                                        }"
                                                        class="flex-1"
                                                    />
                                                </TooltipTrigger>
                                                <TooltipContent side="top" class="max-w-xs">
                                                    <p>{{ passwordHint }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        class="shrink-0 cursor-pointer"
                                                        @click="fillGeneratedPassword"
                                                    >
                                                        <Icon
                                                            name="RefreshCcw"
                                                            class="h-4 w-4"
                                                        />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('password.generate_tooltip') }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                    <p
                                        v-if="form.errors.password"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="password_confirmation">
                                        {{ t('password.repeat') }} *
                                    </Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        :placeholder="t('password.repeat_placeholder')"
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
                                                : t('users.actions.save')
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </TabsContent>

                        <TabsContent value="twofactor" class="space-y-4">
                            <div class="py-8 text-center">
                                <Icon
                                    name="Shield"
                                    class="mx-auto mb-4 h-12 w-12 text-muted-foreground"
                                />
                                <h3 class="text-lg font-medium">
                                    {{ t('users.tabs.two_factor') }}
                                </h3>
                                <p class="text-muted-foreground">
                                    {{ t('users.two_factor_placeholder') }}
                                </p>
                            </div>
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>

            <Dialog
                :open="showGeneratedPasswordDialog"
                @update:open="(open: boolean) => !open && closeGeneratedPasswordDialog()"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('password.generated_title') }}</DialogTitle>
                        <DialogDescription>
                            {{ generatedPasswordDescription }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-2">
                        <Label for="generated-user-password">{{ t('password.label') }}</Label>
                        <Input
                            id="generated-user-password"
                            :model-value="form.password"
                            readonly
                            class="font-mono"
                        />
                    </div>

                    <DialogFooter class="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            class="cursor-pointer"
                            @click="closeGeneratedPasswordDialog"
                        >
                            {{ t('common.close') }}
                        </Button>
                        <Button
                            type="button"
                            class="cursor-pointer"
                            @click="copyGeneratedPassword"
                        >
                            <Icon name="Copy" class="mr-2 h-4 w-4" />
                            {{
                                passwordCopied
                                    ? t('password.copied')
                                    : t('password.copy')
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
