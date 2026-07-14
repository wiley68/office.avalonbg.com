export type ProjectStatus = 'active' | 'completed' | 'deferred';

export type TaskTimelineStatus = 'active' | 'completed' | 'deferred';

export interface ProjectTaskTimelineItem {
    id: number;
    name: string;
    status: TaskTimelineStatus;
}

export interface ProjectListItem {
    id: number;
    name: string;
    description: string | null;
    status: ProjectStatus;
    expected_completion_at: string | null;
    completed_at: string | null;
    created_at: string;
    documents_count: number;
    tasks_count: number;
    email_links_count: number;
    has_git_repository: boolean;
    tasks_timeline: ProjectTaskTimelineItem[];
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

export function taskTimelineSegmentClass(status: TaskTimelineStatus): string {
    switch (status) {
        case 'completed':
            return 'bg-green-500';
        case 'deferred':
            return 'bg-muted-foreground/60';
        default:
            return 'bg-red-500';
    }
}

export function taskTimelineCircleClass(status: TaskTimelineStatus): string {
    switch (status) {
        case 'completed':
            return 'bg-green-500 hover:bg-green-600';
        case 'deferred':
            return 'bg-muted-foreground hover:bg-muted-foreground/80';
        default:
            return 'bg-red-500 hover:bg-red-600';
    }
}

export function formatProjectDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
