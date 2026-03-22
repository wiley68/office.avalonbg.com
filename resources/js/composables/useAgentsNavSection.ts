import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import dashboardRoutes from '@/routes/dashboard';

const agentsNavOpen = ref(false);

/**
 * Споделено състояние за секцията „Агенти“ (sidebar + header).
 * При отваряне на страницата „Бележки“ секцията се разгъва автоматично.
 */
export function useAgentsNavSection(): { agentsNavOpen: typeof agentsNavOpen } {
    const page = usePage();
    const { isCurrentUrl } = useCurrentUrl();
    const notesUrl = dashboardRoutes.notes.url();

    watch(
        () => page.url,
        () => {
            if (isCurrentUrl(notesUrl)) {
                agentsNavOpen.value = true;
            }
        },
        { immediate: true },
    );

    return { agentsNavOpen };
}
