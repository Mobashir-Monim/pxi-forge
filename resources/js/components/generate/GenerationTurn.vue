<script setup lang="ts">
import { CircleAlert, ImagePlus, RotateCcw } from '@lucide/vue';
import { useTimestamp } from '@vueuse/core';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatCharge, formatEstimate } from '@/composables/useCostEstimate';
import { markLetter } from '@/lib/prompt';
import type { Generation, ImageSize } from '@/types';

/** Pixelforge's usual processing time. Past it, the user is told it's taking longer. */
const USUAL_SECONDS = 30;

const props = defineProps<{
    generation: Generation;
    /** Only the latest failed turn of an open chat can be retried. */
    canRetry: boolean;
    /** Images can be picked up as references for the next turn while the chat is open. */
    canReuse: boolean;
    retrying: boolean;
}>();

const emit = defineEmits<{
    retry: [];
    reuse: [url: string, name: string];
}>();

const active = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);

const now = useTimestamp({ interval: 1000 });
// Counted from when Pixelforge accepted it, since time spent waiting for a slot isn't Pixelforge being slow.
const elapsed = computed(() => {
    const since = Date.parse(
        props.generation.accepted_at ?? props.generation.created_at,
    );

    return Math.max(0, Math.floor((now.value - since) / 1000));
});

const frame: Record<ImageSize, string> = {
    '1024x1024': 'aspect-square max-w-sm',
    '1536x1024': 'aspect-[3/2] max-w-md',
    '1024x1536': 'aspect-[2/3] max-w-xs',
};

const statusText = computed(() => {
    const { status, waiting_reason } = props.generation;

    if (status === 'pending') {
        return waiting_reason === 'busy'
            ? 'Waiting for a free slot'
            : waiting_reason === 'overloaded'
              ? 'Generator is busy, retrying'
              : 'Sending';
    }

    return {
        queued: 'Queued',
        processing: 'Generating',
        succeeded: 'Done',
        failed: 'Failed',
    }[status];
});

// No timer on screen, only a note once it runs past Pixelforge's usual time.
const slow = computed(
    () =>
        props.generation.accepted_at !== null && elapsed.value > USUAL_SECONDS,
);

const notes = computed(() =>
    props.generation.references.flatMap((reference, r) =>
        reference.annotations
            .map((mark, m) => ({
                key: `${r}-${m}`,
                label: `${r + 1}${markLetter(m)}`,
                note: mark.note.trim(),
            }))
            .filter((note) => note.note !== ''),
    ),
);

const quality = computed(
    () =>
        props.generation.quality.charAt(0).toUpperCase() +
        props.generation.quality.slice(1),
);

const price = computed(() => {
    const { status, charge, late } = props.generation;

    if (status === 'failed') {
        return 'Not charged';
    }

    if (status === 'succeeded' && charge !== null) {
        return late
            ? 'Free, it finished after we stopped waiting'
            : `Charged ${formatCharge(charge)}`;
    }

    return null;
});
</script>

<template>
    <article class="flex flex-col gap-4">
        <div class="flex flex-col items-end gap-2">
            <ul v-if="generation.references.length" class="flex gap-1.5">
                <li
                    v-for="(reference, index) in generation.references"
                    :key="index"
                    class="group relative size-14 overflow-hidden rounded-lg border"
                >
                    <img
                        :src="reference.url"
                        :alt="`Reference ${index + 1}`"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-1 left-1 flex size-4 items-center justify-center rounded-full bg-background/90 text-[10px] font-semibold"
                        >{{ index + 1 }}</span
                    >
                    <button
                        v-if="canReuse"
                        type="button"
                        class="absolute inset-0 flex items-center justify-center bg-background/70 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                        :aria-label="`Use reference ${index + 1} again`"
                        @click="emit('reuse', reference.url, reference.name)"
                    >
                        <ImagePlus class="size-4" />
                    </button>
                </li>
            </ul>
            <div class="max-w-[80%] rounded-2xl bg-muted px-4 py-2.5 text-sm">
                <p class="whitespace-pre-wrap">{{ generation.message }}</p>
                <ul v-if="notes.length" class="mt-2 flex flex-col gap-1">
                    <li
                        v-for="note in notes"
                        :key="note.key"
                        class="text-xs text-muted-foreground"
                    >
                        <span class="font-semibold text-foreground">{{
                            note.label
                        }}</span>
                        {{ note.note }}
                    </li>
                </ul>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div
                class="relative w-full overflow-hidden rounded-xl border bg-muted/40"
                :class="frame[generation.size]"
                role="status"
                :aria-label="`${statusText} image`"
            >
                <img
                    v-if="
                        generation.status === 'succeeded' &&
                        generation.image_url
                    "
                    :src="generation.image_url"
                    :alt="generation.message"
                    class="size-full object-cover"
                />
                <template v-else>
                    <div
                        v-if="active"
                        class="absolute inset-0 bg-[linear-gradient(110deg,transparent_30%,color-mix(in_oklch,var(--foreground)_8%,transparent)_50%,transparent_70%)] bg-[length:200%_100%] motion-safe:animate-shimmer"
                    />
                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-6 text-center text-sm text-muted-foreground"
                    >
                        <Spinner v-if="active" class="size-5" />
                        <CircleAlert
                            v-else-if="generation.status === 'failed'"
                            class="size-5 text-destructive"
                        />
                        <span class="font-medium text-foreground">{{
                            statusText
                        }}</span>
                        <span v-if="active && slow" class="text-xs">
                            Taking longer than usual
                        </span>
                        <span
                            v-else-if="!active && generation.failure"
                            class="text-xs text-balance"
                            >{{ generation.failure.message }}</span
                        >
                        <span v-else-if="!active" class="text-xs">
                            The image link has expired.
                        </span>
                        <Button
                            v-if="canRetry"
                            size="sm"
                            variant="outline"
                            class="mt-1"
                            :disabled="retrying"
                            @click="emit('retry')"
                        >
                            <RotateCcw />
                            Try again
                        </Button>
                    </div>
                </template>
            </div>
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground tabular-nums"
            >
                <span>
                    {{ generation.size }} · {{ quality }} · Estimated
                    {{ formatEstimate(generation.estimate) }}
                    <template v-if="price"> · {{ price }}</template>
                </span>
                <Button
                    v-if="
                        canReuse &&
                        generation.status === 'succeeded' &&
                        generation.image_url
                    "
                    size="sm"
                    variant="ghost"
                    class="h-7 px-2 text-xs"
                    @click="
                        emit('reuse', generation.image_url, 'Generated image')
                    "
                >
                    <ImagePlus />
                    Use as reference
                </Button>
            </div>
        </div>
    </article>
</template>
