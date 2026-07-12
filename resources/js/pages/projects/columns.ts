import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';

export type ProjectStatus = 'active' | 'completed' | 'deferred';

export interface ProjectListItem {
    id: number;
    name: string;
    description: string | null;
    status: ProjectStatus;
    expected_completion_at: string | null;
    completed_at: string | null;
    created_at: string;
}

type TranslateFn = (
    key: string,
    replace?: Record<string, string>,
) => string;

export function createProjectColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('projects.columns.id'),
        name: t('projects.columns.name'),
        status: t('projects.columns.status'),
        expected_completion_at: t('projects.columns.expected_completion_at'),
        completed_at: t('projects.columns.completed_at'),
        created_at: t('projects.columns.created_at'),
        actions: t('projects.columns.actions'),
    };
}

type ProjectColumnOptions = {
    t: TranslateFn;
    onView: (project: ProjectListItem) => void;
    onEdit: (project: ProjectListItem) => void;
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

const statusBadgeClass = (status: ProjectStatus): string => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-100';
        case 'deferred':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100';
    }
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};

export const createProjectColumns = (
    options: ProjectColumnOptions,
): ColumnDef<ProjectListItem>[] => {
    const { t } = options;

    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('projects.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('id')),
        },
        {
            accessorKey: 'name',
            header: ({ column }) =>
                sortableHeader(t('projects.columns.name'), column),
            cell: ({ row }) => {
                const project = row.original;

                return h(
                    Button,
                    {
                        variant: 'link',
                        class: 'h-auto p-0 font-medium',
                        onClick: () => options.onView(project),
                    },
                    () => project.name,
                );
            },
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('projects.columns.status'), column),
            cell: ({ row }) => {
                const status = row.getValue('status') as ProjectStatus;

                return h(
                    'span',
                    {
                        class: `inline-block rounded px-2 py-1 text-xs font-medium ${statusBadgeClass(status)}`,
                    },
                    t(`projects.status.${status}`),
                );
            },
        },
        {
            accessorKey: 'expected_completion_at',
            header: ({ column }) =>
                sortableHeader(
                    t('projects.columns.expected_completion_at'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    formatDate(row.getValue('expected_completion_at')),
                ),
        },
        {
            accessorKey: 'completed_at',
            header: ({ column }) =>
                sortableHeader(t('projects.columns.completed_at'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    formatDate(row.getValue('completed_at')),
                ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) =>
                sortableHeader(t('projects.columns.created_at'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    formatDate(row.getValue('created_at')),
                ),
        },
        {
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) => {
                const project = row.original;

                return h(TableRowActionsMenu, {
                    actions: [
                        {
                            label: t('common.view'),
                            icon: Eye,
                            onSelect: () => options.onView(project),
                        },
                        {
                            label: t('common.edit'),
                            icon: Pencil,
                            onSelect: () => options.onEdit(project),
                        },
                        {
                            label: t('common.delete'),
                            icon: Trash2,
                            variant: 'destructive',
                            onSelect: () => options.onDelete(project.id),
                        },
                    ],
                });
            },
        },
    ];
};
