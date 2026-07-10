import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as usersIndex } from '@/routes/users';

const usersNavOpen = ref(false);

/**
 * Споделено състояние за секцията „Потребители“ в sidebar.
 * При отваряне на страница от секцията се разгъва автоматично.
 */
export function useUsersNavSection(): { usersNavOpen: typeof usersNavOpen } {
    const page = usePage();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    watch(
        () => page.url,
        () => {
            if (isCurrentOrParentUrl(usersIndex())) {
                usersNavOpen.value = true;
            }
        },
        { immediate: true },
    );

    return { usersNavOpen };
}
