import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import {
    initializeTheme,
    syncThemeFromPageProps,
} from '@/composables/useAppearance';

import { clearSensitiveState, protectFetch } from '@/lib/accessRevocation';
import { dashboard } from '@/routes';

let authorizationContext: string | null = null;
let revokeAccess: (url?: string) => void = () => {};

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        syncThemeFromPageProps(props.initialPage.props);

        authorizationContext = props.initialPage.props.authorizationContext as string | null;
        const app = createApp({ render: () => h(App, props) }).use(plugin);
        app.mount(el);

        let revoked = false;
        revokeAccess = (url = dashboard().url) => {
            if (revoked) {
                return;
            }

            revoked = true;
            router.cancelAll();
            router.flushAll();
            clearSensitiveState();
            app.unmount();
            el.replaceChildren();
            window.location.replace(url);
        };
        window.fetch = protectFetch(window.fetch.bind(window), () => authorizationContext, revokeAccess);
        const verifySession = () => {
            if (authorizationContext !== null) {
                void fetch(dashboard().url, { method: 'HEAD', credentials: 'same-origin', cache: 'no-store' }).catch(() => {});
            }
        };
        window.addEventListener('focus', verifySession);
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                verifySession();
            }
        });
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('before', (event) => {
    if (authorizationContext !== null && event.detail.visit.url.origin === window.location.origin) {
        event.detail.visit.headers['X-Office-Context'] = authorizationContext;
    }
});

router.on('invalid', (event) => {
    if ([401, 403, 404, 419].includes(event.detail.response.status)) {
        event.preventDefault();
        revokeAccess();
    }
});

router.on('navigate', (event) => {
    if (event.detail.page.props.authorizationContext !== authorizationContext) {
        revokeAccess(event.detail.page.url);
    }
});

router.on('success', (event) => {
    const next = event.detail.page.props.authorizationContext as string | null;

    if (authorizationContext !== null && next !== authorizationContext) {
        revokeAccess();

        return;
    }

    authorizationContext = next;
    syncThemeFromPageProps(event.detail.page.props);
});

initializeTheme();
