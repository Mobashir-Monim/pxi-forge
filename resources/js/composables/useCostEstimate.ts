import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { estimate as estimateRoute } from '@/routes/generations';
import type { CostEstimate, ImageQuality, ImageSize } from '@/types';

export type EstimateInput = {
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    referenceImages: number;
};

/** One estimate from the server, null when it can't be priced (say, the prompt is over the limit). */
export async function fetchEstimate(
    { prompt, size, quality, referenceImages }: EstimateInput,
    signal?: AbortSignal,
): Promise<CostEstimate | null> {
    const response = await fetch(
        estimateRoute.url({
            query: { prompt, size, quality, reference_images: referenceImages },
        }),
        { headers: { Accept: 'application/json' }, signal },
    );

    return response.ok ? response.json() : null;
}

/**
 * Keeps a cost estimate for the draft, fetched from the server so the math lives in one place. Only the latest
 * request counts: older ones are cancelled as the user keeps typing.
 */
export function useCostEstimate(input: () => EstimateInput) {
    const estimate = ref<CostEstimate | null>(null);
    const loading = ref(false);
    let controller: AbortController | null = null;

    async function refresh(): Promise<void> {
        controller?.abort();
        controller = new AbortController();
        loading.value = true;

        try {
            estimate.value = await fetchEstimate(input(), controller.signal);
        } catch (error) {
            if ((error as Error).name === 'AbortError') {
                return;
            }

            estimate.value = null;
        }

        loading.value = false;
    }

    // Watches the composed prompt, so typing a note on a mark reprices just like typing in the prompt.
    watchDebounced(input, refresh, { debounce: 300, immediate: true });

    return { estimate, loading };
}

/** Rounded outward to whole cents, so the real price always falls inside what the user sees. */
export function formatEstimate({ min, max }: CostEstimate): string {
    const low = Math.floor(min / 10_000_000);
    const high = Math.ceil(max / 10_000_000);
    const dollars = (cents: number) => `$${(cents / 100).toFixed(2)}`;

    return low === high ? dollars(low) : `${dollars(low)} – ${dollars(high)}`;
}
