import { computed, onMounted, ref, watch } from 'vue';
import { isAccessFailure, useAccessRevocation } from '@/lib/accessRevocation';

type PaginatorResponse<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

type UseApiLoadMoreOptions = {
    endpoint: string;
    perPage?: number;
    sortBy?: string;
    sortDesc?: boolean;
    initialSearch?: string;
    onError?: (message: string) => void;
    autoload?: boolean;
    searchDebounceMs?: number;
};

function debounce<F extends (...args: never[]) => void>(fn: F, delay = 300) {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return (...args: Parameters<F>) => {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(() => fn(...args), delay);
    };
}

export function useApiLoadMore<T = unknown>(opts: UseApiLoadMoreOptions) {
    const {
        endpoint,
        perPage = 12,
        sortBy = 'id',
        sortDesc = true,
        initialSearch = '',
        onError,
        autoload = true,
        searchDebounceMs = 400,
    } = opts;

    const rows = ref<T[]>([]);
    const loading = ref(false);
    const loadingMore = ref(false);
    const search = ref(initialSearch);
    const total = ref(0);
    const page = ref(1);

    let abortController: AbortController | null = null;

    const hasMore = computed(() => rows.value.length < total.value);

    async function fetchPage(targetPage: number, append: boolean): Promise<void> {
        if (append) {
            loadingMore.value = true;
        } else {
            loading.value = true;
        }

        if (abortController) {
            abortController.abort();
        }

        const controller = new AbortController();
        abortController = controller;

        const params = new URLSearchParams({
            page: String(targetPage),
            per_page: String(perPage),
            sort_by: sortBy,
            sort_desc: sortDesc ? '1' : '0',
            search: search.value,
        });

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });

            if (controller.signal.aborted) {
                return;
            }

            if (isAccessFailure(response)) {
                clear();

                return;
            }

            if (!response.ok) {
                const data = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                onError?.(data?.message ?? 'Възникна грешка при зареждане.');

                return;
            }

            const payload = (await response.json()) as PaginatorResponse<T>;

            if (controller.signal.aborted) {
                return;
            }

            if (append) {
                rows.value = (rows.value as T[]).concat(payload.data);
            } else {
                rows.value = payload.data;
            }

            total.value = payload.total;
            page.value = payload.current_page;
        } catch (error) {
            if ((error as Error).name !== 'AbortError') {
                onError?.('Възникна грешка при зареждане.');
            }
        } finally {
            if (abortController === controller) {
                loading.value = false;
                loadingMore.value = false;
            }
        }
    }

    async function fetchData(): Promise<void> {
        page.value = 1;
        await fetchPage(1, false);
    }

    async function loadMore(): Promise<void> {
        if (loading.value || loadingMore.value || !hasMore.value) {
            return;
        }

        await fetchPage(page.value + 1, true);
    }

    const clear = (): void => {
        abortController?.abort();
        rows.value = [];
        loading.value = false;
        total.value = 0;
        page.value = 1;
        loadingMore.value = false;
    };
    useAccessRevocation(clear);

    const debouncedSearch = debounce(() => {
        void fetchData();
    }, searchDebounceMs);

    watch(search, () => {
        debouncedSearch();
    });

    onMounted(() => {
        if (autoload) {
            void fetchData();
        }
    });

    return {
        rows,
        loading,
        loadingMore,
        search,
        total,
        hasMore,
        fetch: fetchData,
        loadMore,
    };
}
