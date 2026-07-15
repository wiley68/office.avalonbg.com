import type { TaskTreeNode } from '@/types/task-tree';

export function filterTaskTreeByStage(
    nodes: TaskTreeNode[],
    stageId: number,
): TaskTreeNode[] {
    return nodes.reduce<TaskTreeNode[]>((result, node) => {
        const filteredChildren = filterTaskTreeByStage(node.children, stageId);

        if (node.project_revision_id === stageId) {
            result.push({
                ...node,
                children: filteredChildren,
            });

            return result;
        }

        result.push(...filteredChildren);

        return result;
    }, []);
}
