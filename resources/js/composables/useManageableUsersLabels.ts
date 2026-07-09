import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
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

export function useManageableUsersLabels(
    manageableRole: MaybeRefOrGetter<UserRole | undefined>,
) {
    const { t } = useTranslations();

    return computed<ManageableUsersLabels>(() => {
        const prefix =
            toValue(manageableRole) === 'admin'
                ? 'users.admin'
                : 'users.office_user';

        return {
            plural: t(`${prefix}.plural`),
            singular: t(`${prefix}.singular`),
            add: t(`${prefix}.add`),
            createTitle: t(`${prefix}.create_title`),
            editTitle: t(`${prefix}.edit_title`),
            empty: t(`${prefix}.empty`),
            deleteConfirm: t(`${prefix}.delete_confirm`),
        };
    });
}
