export type TaskStatus = 'active' | 'completed' | 'deferred';

export type TaskTreeNode = {
    id: number;
    project_id: number;
    parent_id: number | null;
    project_revision_id: number | null;
    revision: { id: number; label: string } | null;
    name: string;
    description: string | null;
    status: TaskStatus;
    sort_order: number;
    completed_at: string | null;
    created_at: string | null;
    documents: { id: number; original_name: string }[];
    children: TaskTreeNode[];
};
