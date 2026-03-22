<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

type UserListItem = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

defineProps<{
    users: UserListItem[];
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
];

const deleteUser = (userId: number): void => {
    if (! window.confirm('Сигурни ли сте, че искате да изтриете този потребител?')) {
        return;
    }

    router.visit(destroy(userId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Users" />

        <div class="space-y-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Users</h1>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 h-4 w-4" />
                        Add user
                    </Link>
                </Button>
            </div>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Created</th>
                            <th class="px-4 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-t border-sidebar-border/60"
                        >
                            <td class="px-4 py-3">{{ user.name }}</td>
                            <td class="px-4 py-3">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Button variant="outline" size="sm" as-child>
                                        <Link :href="edit(user.id)">
                                            <Pencil class="mr-1 h-4 w-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        type="button"
                                        @click="deleteUser(user.id)"
                                    >
                                        <Trash2 class="mr-1 h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
