import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Download, Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';

export interface DocumentListItem {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    description: string | null;
    created_at: string;
}

type TranslateFn = (
    key: string,
    replace?: Record<string, string>,
) => string;

export function createDocumentColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('documents.columns.id'),
        original_name: t('documents.columns.name'),
        size_bytes: t('documents.columns.size'),
        description: t('documents.columns.description'),
        created_at: t('documents.columns.created_at'),
        actions: t('documents.columns.actions'),
    };
}

type DocumentColumnOptions = {
    t: TranslateFn;
    onEdit: (document: DocumentListItem) => void;
    onDelete: (id: number) => void;
    onDownload: (id: number) => void;
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

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export const createDocumentColumns = (
    options: DocumentColumnOptions,
): ColumnDef<DocumentListItem>[] => {
    const { t } = options;

    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('documents.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('id')),
        },
        {
            accessorKey: 'original_name',
            header: ({ column }) =>
                sortableHeader(t('documents.columns.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('original_name')),
        },
        {
            accessorKey: 'size_bytes',
            header: ({ column }) =>
                sortableHeader(t('documents.columns.size'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    formatSize(row.getValue('size_bytes') as number),
                ),
        },
        {
            accessorKey: 'description',
            header: t('documents.columns.description'),
            enableSorting: false,
            cell: ({ row }) => {
                const description = row.getValue('description') as string | null;

                return h(
                    'div',
                    { class: 'max-w-md truncate text-muted-foreground' },
                    description ?? '—',
                );
            },
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) =>
                sortableHeader(t('documents.columns.created_at'), column),
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
                const document = row.original;

                return h(TableRowActionsMenu, {
                    actions: [
                        {
                            label: t('documents.actions.download'),
                            icon: Download,
                            onSelect: () => options.onDownload(document.id),
                        },
                        {
                            label: t('common.edit'),
                            icon: Pencil,
                            onSelect: () => options.onEdit(document),
                        },
                        {
                            label: t('common.delete'),
                            icon: Trash2,
                            variant: 'destructive',
                            onSelect: () => options.onDelete(document.id),
                        },
                    ],
                });
            },
        },
    ];
};
