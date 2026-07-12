import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';

export interface AccessListItem {
    id: number;
    name: string;
    content: string;
    is_encrypted: boolean;
    created_at: string;
}

type TranslateFn = (
    key: string,
    replace?: Record<string, string>,
) => string;

export function createAccessColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('accesses.columns.id'),
        name: t('accesses.columns.name'),
        content: t('accesses.columns.data'),
        is_encrypted: t('accesses.columns.is_encrypted'),
        created_at: t('accesses.columns.created_at'),
        actions: t('accesses.columns.actions'),
    };
}

type AccessColumnOptions = {
    t: TranslateFn;
    onEdit: (access: AccessListItem) => void;
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

const truncateData = (value: string, isEncrypted: boolean, t: TranslateFn) => {
    if (isEncrypted) {
        return t('accesses.fields.encrypted');
    }

    if (value.length <= 80) {
        return value;
    }

    return `${value.slice(0, 80)}…`;
};

export const createAccessColumns = (
    options: AccessColumnOptions,
): ColumnDef<AccessListItem>[] => {
    const { t } = options;

    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('accesses.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('id')),
        },
        {
            accessorKey: 'name',
            header: ({ column }) =>
                sortableHeader(t('accesses.columns.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'content',
            header: t('accesses.columns.data'),
            enableSorting: false,
            cell: ({ row }) => {
                const access = row.original;

                return h(
                    'div',
                    {
                        class: 'max-w-md truncate text-muted-foreground font-mono text-xs',
                    },
                    truncateData(access.content, access.is_encrypted, t),
                );
            },
        },
        {
            accessorKey: 'is_encrypted',
            header: ({ column }) =>
                sortableHeader(t('accesses.columns.is_encrypted'), column),
            cell: ({ row }) => {
                const isEncrypted = row.getValue('is_encrypted') as boolean;

                return h(
                    'span',
                    {
                        class: `inline-block rounded px-2 py-1 text-xs font-medium ${
                            isEncrypted
                                ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100'
                                : 'bg-muted text-muted-foreground'
                        }`,
                    },
                    isEncrypted
                        ? t('accesses.fields.encrypted')
                        : t('accesses.fields.plain'),
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) =>
                sortableHeader(t('accesses.columns.created_at'), column),
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
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const access = row.original;

                return h(TableRowActionsMenu, {
                    actions: [
                        {
                            label: t('common.edit'),
                            icon: Pencil,
                            onSelect: () => options.onEdit(access),
                        },
                        {
                            label: t('common.delete'),
                            icon: Trash2,
                            variant: 'destructive',
                            onSelect: () => options.onDelete(access.id),
                        },
                    ],
                });
            },
        },
    ];
};
