import type { Component } from 'vue';
import {
    GitBranch,
    Layers,
    ListChecks,
    ListTodo,
    Mail,
    Paperclip,
} from 'lucide-vue-next';

export type ProjectSectionTab =
    | 'stages'
    | 'tasks'
    | 'todos'
    | 'documents'
    | 'git'
    | 'email';

export type ProjectTabTheme = {
    icon: Component;
    iconClass: string;
    badgeClass: string;
    tabHoverClass: string;
};

export const PROJECT_TAB_THEMES: Record<ProjectSectionTab, ProjectTabTheme> = {
    stages: {
        icon: Layers,
        iconClass: 'text-amber-600 dark:text-amber-400',
        badgeClass:
            'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
        tabHoverClass:
            'transition-colors hover:bg-amber-100/90 hover:text-amber-950 dark:hover:bg-amber-950/50 dark:hover:text-amber-50',
    },
    tasks: {
        icon: ListTodo,
        iconClass: 'text-blue-600 dark:text-blue-400',
        badgeClass:
            'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100',
        tabHoverClass:
            'transition-colors hover:bg-blue-100/90 hover:text-blue-950 dark:hover:bg-blue-950/50 dark:hover:text-blue-50',
    },
    todos: {
        icon: ListChecks,
        iconClass: 'text-violet-600 dark:text-violet-400',
        badgeClass:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100',
        tabHoverClass:
            'transition-colors hover:bg-violet-100/90 hover:text-violet-950 dark:hover:bg-violet-950/50 dark:hover:text-violet-50',
    },
    documents: {
        icon: Paperclip,
        iconClass: 'text-emerald-600 dark:text-emerald-400',
        badgeClass:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
        tabHoverClass:
            'transition-colors hover:bg-emerald-100/90 hover:text-emerald-950 dark:hover:bg-emerald-950/50 dark:hover:text-emerald-50',
    },
    git: {
        icon: GitBranch,
        iconClass: 'text-fuchsia-600 dark:text-fuchsia-400',
        badgeClass:
            'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-950 dark:text-fuchsia-100',
        tabHoverClass:
            'transition-colors hover:bg-fuchsia-100/90 hover:text-fuchsia-950 dark:hover:bg-fuchsia-950/50 dark:hover:text-fuchsia-50',
    },
    email: {
        icon: Mail,
        iconClass: 'text-sky-600 dark:text-sky-400',
        badgeClass:
            'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100',
        tabHoverClass:
            'transition-colors hover:bg-sky-100/90 hover:text-sky-950 dark:hover:bg-sky-950/50 dark:hover:text-sky-50',
    },
};

export function projectTabTheme(tab: ProjectSectionTab): ProjectTabTheme {
    return PROJECT_TAB_THEMES[tab];
}
