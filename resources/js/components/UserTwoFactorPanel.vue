<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { AlertCircle, Check, Copy, Mail, ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import {
    disableTwoFactor,
    enableTwoFactor,
    resendTwoFactor,
} from '@/composables/useUserTwoFactorRoutes';
import type { TwoFactorManualSetup, TwoFactorNotification } from '@/types';

type PageFlash = {
    two_factor_manual_setup?: TwoFactorManualSetup;
    two_factor_notification?: TwoFactorNotification;
};

const props = defineProps<{
    userId: number;
    userEmail: string;
    twoFactorEnabled: boolean;
}>();

const page = usePage();
const { t } = useTranslations();
const { resolvedAppearance } = useAppearance();
const { showMessage, showError } = useAppToast();
const { copy, copied } = useClipboard();

const isEnabling = ref(false);
const isDisabling = ref(false);
const isResending = ref(false);
const manualSetup = ref<TwoFactorManualSetup | null>(null);
const copiedRecoveryCodes = ref(false);

const routeParams = () => ({
    user: props.userId,
});

const recoveryCodesText = computed(() =>
    manualSetup.value?.recoveryCodes.join('\n') ?? '',
);

const pageFlash = computed(() => page.flash as PageFlash | undefined);

const syncFromFlash = () => {
    const flash = pageFlash.value;

    if (flash?.two_factor_manual_setup) {
        manualSetup.value = flash.two_factor_manual_setup;
    }
};

watch(pageFlash, syncFromFlash, { immediate: true, deep: true });

const showSetupResultMessage = (
    successTitle: string,
    successMessage: string,
) => {
    syncFromFlash();

    const notification = pageFlash.value?.two_factor_notification;

    if (notification?.type === 'sent_to_creator') {
        showMessage(
            successTitle,
            t('users.two_factor.sent_to_creator_message', {
                userEmail: props.userEmail || t('users.two_factor.the_user'),
                sentTo: notification.sent_to,
            }),
        );

        return;
    }

    if (manualSetup.value) {
        showMessage(successTitle, t('users.two_factor.manual_delivery_message'));

        return;
    }

    showMessage(successTitle, successMessage);
};

const onError = () => {
    showError(t('common.error'), t('users.two_factor.operation_failed'));
};

const handleEnable = () => {
    isEnabling.value = true;
    manualSetup.value = null;

    router.post(enableTwoFactor(routeParams()).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showSetupResultMessage(
                t('users.two_factor.enabled_title'),
                t('users.two_factor.instructions_sent', { email: props.userEmail }),
            );
        },
        onError,
        onFinish: () => {
            isEnabling.value = false;
        },
    });
};

const handleDisable = () => {
    isDisabling.value = true;
    manualSetup.value = null;

    router.delete(disableTwoFactor(routeParams()).url, {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(
                t('users.two_factor.disabled_title'),
                t('users.two_factor.disabled_message'),
            );
        },
        onError,
        onFinish: () => {
            isDisabling.value = false;
        },
    });
};

const handleResend = () => {
    isResending.value = true;
    manualSetup.value = null;

    router.post(resendTwoFactor(routeParams()).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showSetupResultMessage(
                t('users.two_factor.email_sent_title'),
                t('users.two_factor.instructions_resent', { email: props.userEmail }),
            );
        },
        onError,
        onFinish: () => {
            isResending.value = false;
        },
    });
};

const copySecretKey = async () => {
    if (!manualSetup.value?.secretKey) {
        return;
    }

    await copy(manualSetup.value.secretKey);
};

const copyRecoveryCodes = async () => {
    if (!recoveryCodesText.value) {
        return;
    }

    await copy(recoveryCodesText.value);
    copiedRecoveryCodes.value = true;

    setTimeout(() => {
        copiedRecoveryCodes.value = false;
    }, 2000);
};
</script>

<template>
    <div class="space-y-4 py-4">
        <div class="flex flex-col items-start gap-4">
            <Badge :variant="twoFactorEnabled ? 'default' : 'destructive'">
                {{
                    twoFactorEnabled
                        ? t('users.two_factor.enabled')
                        : t('users.two_factor.disabled')
                }}
            </Badge>

            <p class="text-sm text-muted-foreground">
                {{
                    t('users.two_factor.description', {
                        email: userEmail || '—',
                    })
                }}
            </p>
        </div>

        <div
            v-if="manualSetup"
            class="space-y-4 rounded-lg border border-destructive/30 bg-destructive/5 p-4"
        >
            <Alert variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>{{ t('users.two_factor.email_not_sent_title') }}</AlertTitle>
                <AlertDescription>
                    {{
                        t('users.two_factor.email_not_sent_description', {
                            reason: manualSetup.reason,
                        })
                    }}
                </AlertDescription>
            </Alert>

            <div class="flex flex-col items-center gap-3">
                <p class="text-sm font-medium">{{ t('users.two_factor.qr_scan') }}</p>
                <div
                    class="overflow-hidden rounded-lg border border-border bg-background p-4"
                >
                    <div
                        v-html="manualSetup.qrCodeSvg"
                        class="flex size-48 items-center justify-center"
                        :style="{
                            filter:
                                resolvedAppearance === 'dark'
                                    ? 'invert(1) brightness(1.5)'
                                    : undefined,
                        }"
                    />
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-sm font-medium">{{ t('users.two_factor.manual_key') }}</p>
                <div
                    class="flex items-stretch overflow-hidden rounded-lg border border-border"
                >
                    <input
                        type="text"
                        readonly
                        :value="manualSetup.secretKey"
                        class="w-full bg-background p-3 font-mono text-sm text-foreground"
                    />
                    <button
                        type="button"
                        class="flex items-center border-l border-border px-3 hover:bg-muted"
                        @click="copySecretKey"
                    >
                        <Check v-if="copied" class="size-4 text-green-500" />
                        <Copy v-else class="size-4" />
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium">
                        {{ t('users.two_factor.recovery_codes') }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="copyRecoveryCodes"
                    >
                        <Check
                            v-if="copiedRecoveryCodes"
                            class="mr-2 size-4 text-green-500"
                        />
                        <Copy v-else class="mr-2 size-4" />
                        {{
                            copiedRecoveryCodes
                                ? t('users.two_factor.copied_all')
                                : t('users.two_factor.copy_all')
                        }}
                    </Button>
                </div>
                <div class="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm">
                    <div
                        v-for="(code, index) in manualSetup.recoveryCodes"
                        :key="index"
                    >
                        {{ code }}
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!twoFactorEnabled" class="flex flex-wrap gap-2">
            <Button
                class="cursor-pointer"
                :disabled="isEnabling"
                @click="handleEnable"
            >
                <ShieldCheck class="mr-2 h-4 w-4" />
                {{
                    isEnabling
                        ? t('users.two_factor.enabling')
                        : t('users.two_factor.enable_and_send')
                }}
            </Button>
        </div>

        <div v-else class="flex flex-wrap gap-2">
            <Button
                variant="outline"
                class="cursor-pointer"
                :disabled="isResending"
                @click="handleResend"
            >
                <Mail class="mr-2 h-4 w-4" />
                {{
                    isResending
                        ? t('users.two_factor.resending')
                        : t('users.two_factor.resend')
                }}
            </Button>

            <Button
                variant="destructive"
                class="cursor-pointer"
                :disabled="isDisabling"
                @click="handleDisable"
            >
                <ShieldBan class="mr-2 h-4 w-4" />
                {{
                    isDisabling
                        ? t('users.two_factor.disabling')
                        : t('users.two_factor.disable')
                }}
            </Button>
        </div>
    </div>
</template>
