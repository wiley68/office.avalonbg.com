<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { rotate } from '@/routes/access-encryption-key';

defineProps<{ encryptionContext: string }>();
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
            :reset-on-success="['current_password']"
            :reset-on-error="['current_password']"
            class="space-y-4 rounded-lg border p-4"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <input
                type="hidden"
                name="encryption_context"
                :value="encryptionContext"
            />
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
            <InputError :message="errors.encryption_context" />
            <InputError :message="errors.content" />
            <div class="flex items-center gap-4">
                <Button type="submit" :disabled="processing">{{
                    t('accesses.encryption_key.rotate_button')
                }}</Button>
                <p
                    v-show="recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('accesses.encryption_key.rotated') }}
                </p>
            </div>
        </Form>
    </div>
</template>
