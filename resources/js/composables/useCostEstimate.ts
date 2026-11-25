import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { estimate as estimateRoute } from '@/routes/generations';
import type { CostEstimate, GenerationDraft } from '@/types';

/**
 * Keeps a cost estimate for the draft, fetched from the server so the math lives in one place. Only the latest
 * request counts: older ones are cancelled as the user keeps typing.
 */
export function useCostEstimate(draft: GenerationDraft) {
    const estimate = ref<CostEstimate | null>(null);
    const loading = ref(false);
    let controller: AbortController | null = null;

    async function refresh(): Promise<void> {
        controller?.abort();
        controller = new AbortController();
        loading.value = true;

        try {
            const response = await fetch(
                estimateRoute.url({
                    query: {
                        prompt: draft.prompt,
                        size: draft.size,
                        quality: draft.quality,
                        reference_images: draft.references.length,
                    },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );

            estimate.value = response.ok ? await response.json() : null;
        } catch (error) {
            if ((error as Error).name === 'AbortError') {
                return;
            }

            estimate.value = null;
        }

        loading.value = false;
    }

    watchDebounced(
        () => [
            draft.prompt,
            draft.size,
            draft.quality,
            draft.references.length,
        ],
        refresh,
        { debounce: 300, immediate: true },
    );

    return { estimate, loading };
}

/** Rounded outward to whole cents, so the real price always falls inside what the user sees. */
export function formatEstimate({ min, max }: CostEstimate): string {
    const low = Math.floor(min / 10_000_000);
    const high = Math.ceil(max / 10_000_000);
    const dollars = (cents: number) => `$${(cents / 100).toFixed(2)}`;

    return low === high ? dollars(low) : `${dollars(low)} – ${dollars(high)}`;
}
