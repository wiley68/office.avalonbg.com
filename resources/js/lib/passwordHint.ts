import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

export const PASSWORD_MIN_LENGTH = 9;

export function usePasswordHint() {
    const { t } = useTranslations();

    return computed(() =>
        t('password.hint', { min: String(PASSWORD_MIN_LENGTH) }),
    );
}
