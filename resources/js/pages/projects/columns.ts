export type ProjectStatus = 'active' | 'completed' | 'deferred';

export interface ProjectListItem {
    id: number;
    name: string;
    description: string | null;
    status: ProjectStatus;
    expected_completion_at: string | null;
    completed_at: string | null;
    created_at: string;
    documents_count: number;
    latest_revision: {
        id: number;
        label: string;
    } | null;
}

export function projectStatusBadgeClass(status: ProjectStatus): string {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-100';
        case 'deferred':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100';
    }
}

export function formatProjectDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
