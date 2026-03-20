<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { edit, index, update } from '@/routes/users';

type EditableUser = {
    id: number;
    name: string;
    email: string;
};

const props = defineProps<{
    user: EditableUser;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Users',
        href: index(),
    },
    {
        title: 'Edit user',
        href: edit(props.user.id),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit user" />

        <div class="mx-auto w-full max-w-2xl space-y-4 p-4">
            <h1 class="text-xl font-semibold">Edit user</h1>

            <Form
                v-bind="update.form(user.id)"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required autocomplete="name" :default-value="user.name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" type="email" name="email" required autocomplete="email" :default-value="user.email" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">New password (optional)</Label>
                    <Input id="password" type="password" name="password" autocomplete="new-password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm new password</Label>
                    <Input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" />
                </div>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="processing">Save</Button>
                    <Button variant="outline" as-child>
                        <Link :href="index()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
