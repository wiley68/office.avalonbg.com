<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { ChevronDown } from 'lucide-vue-next';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxItem,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type ContactOption = {
    id: number;
    name: string | null;
    second_name: string | null;
    last_name: string;
    firm: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: string;
        apiBase?: string;
        disabled?: boolean;
        id?: string;
        placeholder?: string;
        triggerClass?: string;
        perPage?: number;
    }>(),
    {
        apiBase: '/api/contacts',
        disabled: false,
        id: undefined,
        placeholder: 'Изберете контакт…',
        triggerClass: '',
        perPage: 50,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const searchTerm = ref('');
const remoteOptions = ref<ContactOption[]>([]);
const searchLoading = ref(false);
const selectedSnapshot = ref<ContactOption | null>(null);
const comboboxOpen = ref(false);

const searchInputRef = ref<InstanceType<typeof Input> | null>(null);

let listAbort: AbortController | null = null;
let selectedAbort: AbortController | null = null;

const contactLabel = (c: ContactOption): string => {
    const name = [c.name, c.second_name, c.last_name].filter(Boolean).join(' ');
    const parts = [c.firm?.trim() || null, name.trim() || null].filter(Boolean);

    return parts.length > 0 ? parts.join(' — ') : `#${c.id}`;
};

const byId = computed(() => {
    const m = new Map<number, ContactOption>();

    for (const c of remoteOptions.value) {
        m.set(c.id, c);
    }

    if (selectedSnapshot.value) {
        m.set(selectedSnapshot.value.id, selectedSnapshot.value);
    }

    return m;
});

const selectedId = computed<number | undefined>({
    get() {
        const n = Number(props.modelValue);

        return Number.isInteger(n) && n > 0 ? n : undefined;
    },
    set(v) {
        emit(
            'update:modelValue',
            v === undefined || v === null ? '' : String(v),
        );
    },
});

const triggerLabel = computed(() => {
    const id = selectedId.value;

    if (id === undefined) {
        return '';
    }

    const c = byId.value.get(id);

    return c ? `${contactLabel(c)} (#${c.id})` : '';
});

const triggerPending = computed(() => {
    const raw = props.modelValue.trim();

    if (raw === '') {
        return false;
    }

    const id = Number(raw);

    if (!Number.isInteger(id) || id <= 0) {
        return false;
    }

    return selectedSnapshot.value?.id !== id;
});

async function fetchContactById(id: number): Promise<void> {
    selectedAbort?.abort();
    const controller = new AbortController();
    selectedAbort = controller;

    try {
        const response = await fetch(`${props.apiBase}/${id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as { data: ContactOption };

        if (!controller.signal.aborted) {
            selectedSnapshot.value = json.data;
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }
    }
}

watch(
    () => props.modelValue,
    async (raw) => {
        const id = Number(raw);

        if (!Number.isInteger(id) || id <= 0) {
            selectedSnapshot.value = null;

            return;
        }

        const fromRemote = remoteOptions.value.find((c) => c.id === id);

        if (fromRemote) {
            selectedSnapshot.value = fromRemote;

            return;
        }

        if (selectedSnapshot.value?.id === id) {
            return;
        }

        await fetchContactById(id);
    },
    { immediate: true },
);

watch(remoteOptions, (list) => {
    const id = Number(props.modelValue);

    if (!Number.isInteger(id) || id <= 0) {
        return;
    }

    const hit = list.find((c) => c.id === id);

    if (hit) {
        selectedSnapshot.value = hit;
    }
});

async function fetchContactsList(query: string): Promise<void> {
    listAbort?.abort();
    const controller = new AbortController();
    listAbort = controller;
    searchLoading.value = true;

    try {
        const params = new URLSearchParams({
            page: '1',
            per_page: String(props.perPage),
        });
        const q = query.trim();

        if (q.length > 0) {
            params.set('q', q);
        }

        const response = await fetch(`${props.apiBase}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            if (!controller.signal.aborted) {
                remoteOptions.value = [];
            }

            return;
        }

        const json = (await response.json()) as { data?: ContactOption[] };

        if (!controller.signal.aborted) {
            remoteOptions.value = json.data ?? [];
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        if (!controller.signal.aborted) {
            remoteOptions.value = [];
        }
    } finally {
        if (!controller.signal.aborted) {
            searchLoading.value = false;
        }
    }
}

const debouncedFetchList = useDebounceFn((query: string) => {
    void fetchContactsList(query);
}, 300);

function onSearchInput(): void {
    debouncedFetchList(searchTerm.value.trim());
}

function focusSearchInput(): void {
    const root = searchInputRef.value as unknown as {
        $el?: HTMLElement;
    } | null;
    const el = root?.$el;

    if (el && typeof (el as HTMLInputElement).focus === 'function') {
        (el as HTMLInputElement).focus({ preventScroll: true });
    }
}

watch(comboboxOpen, async (open) => {
    if (!open) {
        return;
    }

    searchTerm.value = '';
    await fetchContactsList('');
    await nextTick();
    focusSearchInput();
});

onBeforeUnmount(() => {
    listAbort?.abort();
    selectedAbort?.abort();
});
</script>

<template>
    <ComboboxRoot
        v-model="selectedId"
        v-model:open="comboboxOpen"
        :ignore-filter="true"
        :disabled="disabled"
        :open-on-focus="false"
        :open-on-click="false"
        class="w-full"
    >
        <ComboboxAnchor class="block w-full">
            <ComboboxTrigger as-child>
                <button
                    type="button"
                    :id="id"
                    :disabled="disabled"
                    :class="
                        cn(
                            'flex h-9 w-full cursor-default items-center justify-between gap-2 rounded-md border border-input bg-background px-3 py-1 text-left text-sm shadow-xs ring-offset-background',
                            'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                            triggerClass,
                        )
                    "
                    :aria-expanded="comboboxOpen"
                    aria-haspopup="listbox"
                >
                    <span
                        v-if="triggerPending"
                        class="min-w-0 flex-1 truncate text-muted-foreground"
                    >
                        Зареждане…
                    </span>
                    <span
                        v-else-if="triggerLabel"
                        class="min-w-0 flex-1 truncate text-foreground"
                    >
                        {{ triggerLabel }}
                    </span>
                    <span
                        v-else
                        class="min-w-0 flex-1 truncate text-muted-foreground"
                    >
                        {{ placeholder }}
                    </span>
                    <ChevronDown
                        class="h-4 w-4 shrink-0 opacity-70 transition-transform data-[state=open]:rotate-180"
                        :data-state="comboboxOpen ? 'open' : 'closed'"
                    />
                </button>
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-100 w-(--reka-combobox-anchor-width) overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0"
                :avoid-collisions="true"
            >
                <div
                    class="border-b border-border p-2"
                    @pointerdown.capture.stop
                >
                    <Input
                        ref="searchInputRef"
                        v-model="searchTerm"
                        type="search"
                        placeholder="Търсене по име, фирма, №…"
                        class="h-8 text-sm"
                        autocomplete="off"
                        @input="onSearchInput"
                        @keydown.escape.stop="comboboxOpen = false"
                    />
                </div>
                <ComboboxViewport
                    class="max-h-[min(40vh,280px)] overflow-y-auto p-1"
                >
                    <template
                        v-if="searchLoading && remoteOptions.length === 0"
                    >
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Зареждане…
                        </div>
                    </template>
                    <template
                        v-else-if="!searchLoading && remoteOptions.length === 0"
                    >
                        <div
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            Няма намерени контакти.
                        </div>
                    </template>
                    <template v-else>
                        <ComboboxItem
                            v-for="c in remoteOptions"
                            :key="c.id"
                            class="relative flex cursor-default items-center rounded-sm px-2 py-1.5 text-sm outline-none select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                            :value="c.id"
                            :text-value="`${contactLabel(c)} (#${c.id})`"
                        >
                            {{ contactLabel(c) }} (#{{ c.id }})
                        </ComboboxItem>
                    </template>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
