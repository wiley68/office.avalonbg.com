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
};

export const PROJECT_TAB_THEMES: Record<ProjectSectionTab, ProjectTabTheme> = {
    stages: {
        icon: Layers,
        iconClass: 'text-amber-600 dark:text-amber-400',
        badgeClass:
            'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
    },
    tasks: {
        icon: ListTodo,
        iconClass: 'text-blue-600 dark:text-blue-400',
        badgeClass:
            'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100',
    },
    todos: {
        icon: ListChecks,
        iconClass: 'text-violet-600 dark:text-violet-400',
        badgeClass:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100',
    },
    documents: {
        icon: Paperclip,
        iconClass: 'text-emerald-600 dark:text-emerald-400',
        badgeClass:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    },
    git: {
        icon: GitBranch,
        iconClass: 'text-fuchsia-600 dark:text-fuchsia-400',
        badgeClass:
            'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-950 dark:text-fuchsia-100',
    },
    email: {
        icon: Mail,
        iconClass: 'text-sky-600 dark:text-sky-400',
        badgeClass:
            'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100',
    },
};

export function projectTabTheme(tab: ProjectSectionTab): ProjectTabTheme {
    return PROJECT_TAB_THEMES[tab];
}
