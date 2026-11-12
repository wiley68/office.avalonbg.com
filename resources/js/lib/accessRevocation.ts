import { onUnmounted } from 'vue';

const eventName = 'office:access-revoked';

export function clearSensitiveState(): void {
    window.dispatchEvent(new Event(eventName));
}

export function useAccessRevocation(clear: () => void): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener(eventName, clear);
    onUnmounted(() => {
        window.removeEventListener(eventName, clear);
        clear();
    });
}

export function isAccessFailure(response: Pick<Response, 'status' | 'redirected'>): boolean {
    return response.redirected || [401, 403, 404, 419].includes(response.status);
}

export function protectFetch(
    original: typeof fetch,
    context: () => string | null,
    revoke: () => void,
): typeof fetch {
    let revoked = false;

    return async (input, init) => {
        const url = new URL(input instanceof Request ? input.url : String(input), window.location.href);

        if (url.origin !== window.location.origin) {
            return original(input, init);
        }

        if (revoked) {
            throw new DOMException('Access revoked', 'AbortError');
        }

        const headers = new Headers(init?.headers ?? (input instanceof Request ? input.headers : undefined));
        const value = context();

        if (value !== null) {
            headers.set('X-Office-Context', value);
        }

        const response = await original(input, { ...init, headers });

        if (response.redirected || [401, 403, 419].includes(response.status)) {
            revoked = true;
            revoke();
        }

        if (revoked) {
            throw new DOMException('Access revoked', 'AbortError');
        }

        return response;
    };
}
