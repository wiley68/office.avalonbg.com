import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { UserRole } from '@/types';

type ManageableUsersLabels = {
    plural: string;
    singular: string;
    add: string;
    createTitle: string;
    editTitle: string;
    empty: string;
    deleteConfirm: string;
};

const adminLabels: ManageableUsersLabels = {
    plural: 'Администратори',
    singular: 'Администратор',
    add: 'Добави администратор',
    createTitle: 'Нов администратор',
    editTitle: 'Редакция на администратор',
    empty: 'Няма намерени администратори.',
    deleteConfirm:
        'Сигурни ли сте, че искате да изтриете този администратор?',
};

const userLabels: ManageableUsersLabels = {
    plural: 'Потребители',
    singular: 'Потребител',
    add: 'Добави потребител',
    createTitle: 'Нов потребител',
    editTitle: 'Редакция на потребител',
    empty: 'Няма намерени потребители.',
    deleteConfirm: 'Сигурни ли сте, че искате да изтриете този потребител?',
};

export function useManageableUsersLabels(
    manageableRole: MaybeRefOrGetter<UserRole | undefined>,
) {
    return computed(() => {
        if (toValue(manageableRole) === 'admin') {
            return adminLabels;
        }

        return userLabels;
    });
}
