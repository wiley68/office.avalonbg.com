<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { rotate, store } from '@/routes/access-encryption-key';

type Props = {
    hasAccessEncryptionKey?: boolean;
};

withDefaults(defineProps<Props>(), {
    hasAccessEncryptionKey: false,
});

const { t } = useTranslations();

const showRotateForm = ref(false);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('accesses.encryption_key.title')"
            :description="t('accesses.encryption_key.description')"
        />

        <p
            v-if="hasAccessEncryptionKey"
            class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100"
        >
            {{ t('accesses.encryption_key.already_set') }}
        </p>

        <Form
            v-if="!hasAccessEncryptionKey"
            v-bind="store.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            class="space-y-4"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="grid gap-2">
                <Label for="encryption_key">{{
                    t('accesses.encryption_key.field')
                }}</Label>
                <PasswordInput
                    id="encryption_key"
                    name="encryption_key"
                    autocomplete="new-password"
                    :placeholder="t('accesses.encryption_key.field')"
                />
                <InputError :message="errors.encryption_key" />
            </div>

            <div class="grid gap-2">
                <Label for="encryption_key_confirmation">{{
                    t('accesses.encryption_key.confirm_field')
                }}</Label>
                <PasswordInput
                    id="encryption_key_confirmation"
                    name="encryption_key_confirmation"
                    autocomplete="new-password"
                    :placeholder="t('accesses.encryption_key.confirm_field')"
                />
                <InputError :message="errors.encryption_key_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">
                    {{ t('accesses.encryption_key.save') }}
                </Button>
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('accesses.encryption_key.saved') }}
                </p>
            </div>
        </Form>

        <div v-else class="space-y-4">
            <Button
                type="button"
                variant="outline"
                @click="showRotateForm = !showRotateForm"
            >
                {{ t('accesses.encryption_key.rotate_button') }}
            </Button>

            <Form
                v-if="showRotateForm"
                v-bind="rotate.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="[
                    'current_password',
                    'encryption_key',
                    'encryption_key_confirmation',
                ]"
                class="space-y-4 rounded-lg border p-4"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <p class="text-sm text-muted-foreground">
                    {{ t('accesses.encryption_key.rotate_description') }}
                </p>

                <div class="grid gap-2">
                    <Label for="rotate_current_password">{{
                        t('accesses.encryption_key.current_password')
                    }}</Label>
                    <PasswordInput
                        id="rotate_current_password"
                        name="current_password"
                        autocomplete="current-password"
                    />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-2">
                    <Label for="rotate_encryption_key">{{
                        t('accesses.encryption_key.field')
                    }}</Label>
                    <PasswordInput
                        id="rotate_encryption_key"
                        name="encryption_key"
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.encryption_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="rotate_encryption_key_confirmation">{{
                        t('accesses.encryption_key.confirm_field')
                    }}</Label>
                    <PasswordInput
                        id="rotate_encryption_key_confirmation"
                        name="encryption_key_confirmation"
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.encryption_key_confirmation" />
                </div>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="processing">
                        {{ t('accesses.encryption_key.rotate_button') }}
                    </Button>
                    <p
                        v-show="recentlySuccessful"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('accesses.encryption_key.rotated') }}
                    </p>
                </div>
            </Form>
        </div>
    </div>
</template>
