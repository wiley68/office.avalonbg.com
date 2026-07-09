import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { update as updateAppearanceRoute } from '@/routes/appearance';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    updateProfileAppearance: (value: Appearance) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

function applyAppearance(value: Appearance): void {
    appearance.value = value;
    localStorage.setItem('appearance', value);
    setCookie('appearance', value);
    updateTheme(value);
}

function resolveInitialAppearance(
    isAuthenticated: boolean,
    serverAppearance: Appearance | null,
): Appearance {
    if (isAuthenticated) {
        return serverAppearance ?? 'system';
    }

    return getStoredAppearance() ?? serverAppearance ?? 'system';
}

export function useAppearance(): UseAppearanceReturn {
    const page = usePage();

    onMounted(() => {
        const isAuthenticated = page.props.auth.user !== null;
        const serverAppearance = page.props.appearance as Appearance | null;
        const initialAppearance = resolveInitialAppearance(
            isAuthenticated,
            serverAppearance,
        );

        applyAppearance(initialAppearance);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance): void {
        applyAppearance(value);
    }

    function updateProfileAppearance(value: Appearance): void {
        applyAppearance(value);

        if (page.props.auth.user === null) {
            return;
        }

        router.patch(
            updateAppearanceRoute.url(),
            { appearance: value },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        updateProfileAppearance,
    };
}
