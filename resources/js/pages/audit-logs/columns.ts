import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowUpDown, Eye, Trash2 } from 'lucide-vue-next';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

export interface AuditLogDetail {
    поле: string;
    стойност?: string | null;
    начална_стойност?: string | null;
    крайна_стойност?: string | null;
}

export interface AuditLog {
    id: number;
    occurred_at: string;
    event_type:
        | 'login_success'
        | 'login_failed'
        | 'two_factor_challenge_success'
        | 'two_factor_challenge_failed';
    event_type_label: string;
    event_source: 'office' | 'api';
    event_source_label: string;
    is_success: boolean;
    user_id: number | null;
    user_email: string;
    user_name: string;
    details: AuditLogDetail[];
    details_count: number;
}

export const auditFieldLabelMap: Record<string, string> = {
    имейл: 'Имейл',
    причина: 'Причина',
};

export const auditLogColumnTitleMap: Record<string, string> = {
    id: '№',
    occurred_at: 'Дата',
    event_type: 'Събитие',
    event_source: 'Източник',
    is_success: 'Резултат',
    user_name: 'Потребител',
    user_email: 'Имейл',
    details_count: 'Детайли',
    actions: 'Действия',
};

type AuditLogColumnOptions = {
    onViewDetails: (log: AuditLog) => void;
    onDelete: (id: number) => void;
    isRowSelected: (id: number) => boolean;
    onToggleRow: (id: number, checked: boolean) => void;
    isAllSelected: () => boolean;
    onToggleAll: (checked: boolean) => void;
};

const eventTypeVariant = (type: AuditLog['event_type']) => {
    switch (type) {
        case 'login_success':
        case 'two_factor_challenge_success':
            return 'default';
        case 'login_failed':
        case 'two_factor_challenge_failed':
            return 'destructive';
        default:
            return 'outline';
    }
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

export const createAuditLogColumns = (
    options: AuditLogColumnOptions,
): ColumnDef<AuditLog>[] => [
    {
        id: 'select',
        enableHiding: false,
        enableSorting: false,
        header: () =>
            h(Checkbox, {
                modelValue: options.isAllSelected(),
                'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                    options.onToggleAll(value === true),
            }),
        cell: ({ row }) =>
            h(Checkbox, {
                modelValue: options.isRowSelected(row.original.id),
                'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                    options.onToggleRow(row.original.id, value === true),
            }),
    },
    {
        accessorKey: 'id',
        header: ({ column }) => sortableHeader('№', column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('id')),
    },
    {
        accessorKey: 'occurred_at',
        header: ({ column }) => sortableHeader('Дата', column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'whitespace-nowrap' },
                row.getValue('occurred_at'),
            ),
    },
    {
        accessorKey: 'event_type',
        header: ({ column }) => sortableHeader('Събитие', column),
        cell: ({ row }) => {
            const log = row.original;

            return h(
                Badge,
                { variant: eventTypeVariant(log.event_type) },
                () => log.event_type_label,
            );
        },
    },
    {
        accessorKey: 'event_source',
        header: ({ column }) => sortableHeader('Източник', column),
        cell: ({ row }) => h('div', row.original.event_source_label),
    },
    {
        accessorKey: 'is_success',
        header: ({ column }) => sortableHeader('Резултат', column),
        cell: ({ row }) => {
            const success = row.getValue('is_success') as boolean;

            return h(
                Badge,
                { variant: success ? 'default' : 'destructive' },
                () => (success ? 'Успех' : 'Неуспех'),
            );
        },
    },
    {
        accessorKey: 'user_name',
        header: ({ column }) => sortableHeader('Потребител', column),
        cell: ({ row }) => h('div', row.getValue('user_name')),
    },
    {
        accessorKey: 'user_email',
        header: ({ column }) => sortableHeader('Имейл', column),
        cell: ({ row }) =>
            h(
                'div',
                {
                    class: 'max-w-[220px] truncate',
                    title: row.getValue('user_email'),
                },
                row.getValue('user_email'),
            ),
    },
    {
        accessorKey: 'details_count',
        header: 'Детайли',
        cell: ({ row }) => h('div', String(row.getValue('details_count'))),
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const log = row.original;

            return h(TableRowActionsMenu, {
                actions: [
                    {
                        label: 'Преглед',
                        icon: Eye,
                        onSelect: () => options.onViewDetails(log),
                    },
                    {
                        label: 'Изтрий',
                        icon: Trash2,
                        variant: 'destructive',
                        onSelect: () => options.onDelete(log.id),
                    },
                ],
            });
        },
    },
];
