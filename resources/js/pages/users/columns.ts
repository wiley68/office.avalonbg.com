import { router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit } from '@/routes/users';

export interface UserListItem {
    id: number;
    name: string;
    email: string;
    status: number;
    created_at: string;
}

type TranslateFn = (
    key: string,
    replace?: Record<string, string>,
) => string;

export function createUserColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('users.columns.id'),
        name: t('users.columns.name'),
        email: t('users.columns.email'),
        status: t('users.columns.status'),
        created_at: t('users.columns.created_at'),
        actions: t('users.columns.actions'),
    };
}

type UserColumnOptions = {
    t: TranslateFn;
    onDelete: (id: number) => void;
};

const sortableHeader = (
    label: string,
    column: {
        toggleSorting: (desc: boolean) => void;
        getIsSorted: () => false | 'asc' | 'desc';
    },
) =>
    h(
        Button,
        {
            variant: 'ghost',
            onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            class: 'h-8 px-2 lg:px-3',
        },
        () => [label, h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
    );

const statusBadge = (t: TranslateFn, status: number) => {
    const isActive = status === 1;
    const label = isActive
        ? t('users.fields.status_active')
        : t('users.fields.status_inactive');

    const badge = h(
        'span',
        {
            class: `inline-block min-w-[60px] rounded px-2 py-1 text-center text-sm font-medium ${
                isActive
                    ? 'bg-green-600 text-white'
                    : 'cursor-default bg-gray-200 text-gray-700'
            }`,
        },
        label,
    );

    if (isActive) {
        return badge;
    }

    return h(TooltipProvider, { delayDuration: 200 }, () =>
        h(Tooltip, {}, () => [
            h(TooltipTrigger, { asChild: true }, () => badge),
            h(TooltipContent, { side: 'top', class: 'max-w-xs' }, () =>
                h('p', t('users.fields.status_inactive_tooltip')),
            ),
        ]),
    );
};

export const createUserColumns = (
    options: UserColumnOptions,
): ColumnDef<UserListItem>[] => {
    const { t } = options;

    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('users.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('id')),
        },
        {
            accessorKey: 'name',
            header: ({ column }) =>
                sortableHeader(t('users.columns.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'email',
            header: ({ column }) =>
                sortableHeader(t('users.columns.email'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'lowercase text-muted-foreground' },
                    row.getValue('email'),
                ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) =>
                sortableHeader(t('users.columns.created_at'), column),
            cell: ({ row }) => {
                const value = row.getValue('created_at') as string;

                return h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    new Date(value).toLocaleDateString(),
                );
            },
        },
        {
            accessorKey: 'status',
            header: t('users.columns.status'),
            enableSorting: false,
            cell: ({ row }) =>
                statusBadge(t, row.getValue('status') as number),
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const user = row.original;

                return h(TableRowActionsMenu, {
                    actions: [
                        {
                            label: t('common.edit'),
                            icon: Pencil,
                            onSelect: () => router.visit(edit(user.id)),
                        },
                        {
                            label: t('common.delete'),
                            icon: Trash2,
                            variant: 'destructive',
                            onSelect: () => options.onDelete(user.id),
                        },
                    ],
                });
            },
        },
    ];
};
