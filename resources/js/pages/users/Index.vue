<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { useManageableUsersLabels } from '@/composables/useManageableUsersLabels';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { create, destroy, edit, index } from '@/routes/users';
import type { BreadcrumbItem, UserRole } from '@/types';

type UserListItem = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

const props = defineProps<{
    users: UserListItem[];
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
]);

const showDeleteDialog = ref(false);
const userToDelete = ref<number | null>(null);

const requestDeleteUser = (userId: number): void => {
    userToDelete.value = userId;
    showDeleteDialog.value = true;
};

const cancelDelete = (): void => {
    userToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (userToDelete.value === null) {
        return;
    }

    const userId = userToDelete.value;
    userToDelete.value = null;
    showDeleteDialog.value = false;

    router.visit(destroy(userId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="labels.plural" />

        <div class="space-y-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ labels.plural }}</h1>
                <Button as-child>
                    <Link :href="create()">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ labels.add }}
                    </Link>
                </Button>
            </div>

            <div class="overflow-hidden rounded-lg border border-sidebar-border/70">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Име</th>
                            <th class="px-4 py-3 font-medium">Имейл</th>
                            <th class="px-4 py-3 font-medium">Създаден</th>
                            <th class="w-10 px-4 py-3 font-medium" />
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
                                <TableRowActionsMenu
                                    :actions="[
                                        {
                                            label: 'Редакция',
                                            icon: Pencil,
                                            onSelect: () =>
                                                router.visit(edit(user.id)),
                                        },
                                        {
                                            label: 'Изтриване',
                                            icon: Trash2,
                                            variant: 'destructive',
                                            onSelect: () =>
                                                requestDeleteUser(user.id),
                                        },
                                    ]"
                                />
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                {{ labels.empty }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            title="Потвърждение за изтриване"
            :description="labels.deleteConfirm"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
