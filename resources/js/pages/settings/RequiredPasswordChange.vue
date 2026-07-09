<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import RequiredPasswordChangeController from '@/actions/App/Http/Controllers/Settings/RequiredPasswordChangeController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import AuthBase from '@/layouts/AuthLayout.vue';
import { usePasswordHint } from '@/lib/passwordHint';

const { t } = useTranslations();
const passwordHint = usePasswordHint();
</script>

<template>
    <AuthBase
        :title="t('password.must_change.title')"
        :description="t('password.must_change.description')"
    >
        <Head :title="t('password.must_change.title')" />

        <Form
            v-bind="RequiredPasswordChangeController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'current_password',
                'password',
                'password_confirmation',
            ]"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="current_password">{{
                        t('password.must_change.current')
                    }}</Label>
                    <PasswordInput
                        id="current_password"
                        name="current_password"
                        required
                        autofocus
                        autocomplete="current-password"
                        :placeholder="t('password.must_change.current_placeholder')"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('password.new') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        :placeholder="t('password.placeholder')"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ passwordHint }}
                    </p>
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{
                        t('password.repeat_new')
                    }}</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        :placeholder="t('password.repeat_new_placeholder')"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                {{ t('password.must_change.submit') }}
            </Button>

            <p
                v-show="recentlySuccessful"
                class="text-center text-sm text-muted-foreground"
            >
                {{ t('password.must_change.saved') }}
            </p>
        </Form>
    </AuthBase>
</template>
