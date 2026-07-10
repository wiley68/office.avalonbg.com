import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import dashboardRoutes from '@/routes/dashboard';

const agentsNavOpen = ref(false);

const agentSectionUrls = [
    dashboardRoutes.notes.url(),
    dashboardRoutes.admin.statistics.url(),
    dashboardRoutes.admin.export.url(),
];

/**
 * Споделено състояние за секцията „Агенти“ (sidebar + header).
 * При отваряне на страница от секцията се разгъва автоматично.
 */
export function useAgentsNavSection(): { agentsNavOpen: typeof agentsNavOpen } {
    const page = usePage();
    const { isCurrentUrl } = useCurrentUrl();

    watch(
        () => page.url,
        () => {
            if (agentSectionUrls.some((url) => isCurrentUrl(url))) {
                agentsNavOpen.value = true;
            }
        },
        { immediate: true },
    );

    return { agentsNavOpen };
}
