<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useManageableUsersLabels } from '@/composables/useManageableUsersLabels';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/users';
import type { BreadcrumbItem, UserRole } from '@/types';

const props = defineProps<{
    manageableRole?: UserRole;
}>();

const labels = useManageableUsersLabels(() => props.manageableRole);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Табло',
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="labels.createTitle" />

        <div class="mx-auto w-full max-w-2xl space-y-4 p-4">
            <h1 class="text-xl font-semibold">{{ labels.createTitle }}</h1>

            <Form v-bind="store.form()" class="space-y-4" v-slot="{ errors, processing }">
                <div class="grid gap-2">
                    <Label for="name">Име</Label>
                    <Input id="name" name="name" required autocomplete="name" placeholder="Пълно име" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Имейл</Label>
                    <Input id="email" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Парола</Label>
                    <Input id="password" type="password" name="password" required autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Потвърди паролата</Label>
                    <Input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing">Създай</Button>
                    <Button variant="outline" as-child>
                        <Link :href="index()">Отказ</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
