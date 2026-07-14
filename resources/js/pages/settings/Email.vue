<script setup lang="ts">
import { Form, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, PlugZap } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit as editEmail, test as testEmailConnection, update as updateEmail } from '@/routes/email';
import type { BreadcrumbItem } from '@/types';

type ImapAccountConfig = {
    host: string;
    port: number;
    encryption: 'ssl' | 'tls' | 'none';
    username: string;
    default_folder: string;
    has_password: boolean;
    last_verified_at: string | null;
};

type Props = {
    imapAccount: ImapAccountConfig | null;
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();
const page = usePage();

const testing = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.email.title'),
        href: editEmail(),
    },
];

const form = useForm({
    host: props.imapAccount?.host ?? '',
    port: props.imapAccount?.port ?? 993,
    encryption: props.imapAccount?.encryption ?? 'ssl',
    username: props.imapAccount?.username ?? page.props.auth.user?.email ?? '',
    password: '',
    default_folder: props.imapAccount?.default_folder ?? 'INBOX',
});

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const testConnection = async (): Promise<void> => {
    testing.value = true;

    try {
        const response = await fetch(testEmailConnection().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        const payload = (await response.json()) as { message?: string };

        if (!response.ok) {
            showError(t('common.error'), payload.message ?? t('settings.email.test_failed'));

            return;
        }

        showMessage(t('common.success'), payload.message ?? t('settings.email.test_success'));
    } catch {
        showError(t('common.error'), t('settings.email.test_failed'));
    } finally {
        testing.value = false;
    }
};

const saveSettings = (): void => {
    form.put(updateEmail().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            router.reload({ only: ['imapAccount'] });
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.email.title')" />

        <h1 class="sr-only">{{ t('settings.email.title') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    :title="t('settings.email.title')"
                    :description="t('settings.email.description')"
                />

                <Form
                    class="space-y-6"
                    @submit.prevent="saveSettings"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="imap-host">{{ t('settings.email.fields.host') }}</Label>
                            <Input
                                id="imap-host"
                                v-model="form.host"
                                placeholder="imap.example.com"
                                required
                            />
                            <InputError :message="form.errors.host" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="imap-port">{{ t('settings.email.fields.port') }}</Label>
                            <Input
                                id="imap-port"
                                v-model.number="form.port"
                                type="number"
                                min="1"
                                max="65535"
                                required
                            />
                            <InputError :message="form.errors.port" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('settings.email.fields.encryption') }}</Label>
                            <Select v-model="form.encryption">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ssl">
                                        {{ t('settings.email.encryption.ssl') }}
                                    </SelectItem>
                                    <SelectItem value="tls">
                                        {{ t('settings.email.encryption.tls') }}
                                    </SelectItem>
                                    <SelectItem value="none">
                                        {{ t('settings.email.encryption.none') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.encryption" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="imap-username">{{ t('settings.email.fields.username') }}</Label>
                            <Input
                                id="imap-username"
                                v-model="form.username"
                                autocomplete="username"
                                required
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="imap-password">{{ t('settings.email.fields.password') }}</Label>
                            <PasswordInput
                                id="imap-password"
                                v-model="form.password"
                                autocomplete="new-password"
                                :placeholder="imapAccount?.has_password
                                    ? t('settings.email.fields.password_configured')
                                    : t('settings.email.fields.password_required')"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="imap-folder">{{ t('settings.email.fields.default_folder') }}</Label>
                            <Input
                                id="imap-folder"
                                v-model="form.default_folder"
                                placeholder="INBOX"
                                required
                            />
                            <InputError :message="form.errors.default_folder" />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ t('common.save') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="testing || !imapAccount"
                            @click="testConnection"
                        >
                            <Loader2
                                v-if="testing"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            <PlugZap v-else class="mr-2 h-4 w-4" />
                            {{ t('settings.email.test_connection') }}
                        </Button>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
