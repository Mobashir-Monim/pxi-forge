<script setup lang="ts">
import { useTimestamp } from '@vueuse/core';
import { computed } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import { formatEstimate } from '@/composables/useCostEstimate';
import { markLetter } from '@/lib/prompt';
import type { GenerationTurn, ImageSize } from '@/types';

const props = defineProps<{ turn: GenerationTurn }>();

const now = useTimestamp({ interval: 1000 });
const elapsed = computed(() =>
    Math.max(0, Math.floor((now.value - props.turn.createdAt) / 1000)),
);

const frame: Record<ImageSize, string> = {
    '1024x1024': 'aspect-square max-w-sm',
    '1536x1024': 'aspect-[3/2] max-w-md',
    '1024x1536': 'aspect-[2/3] max-w-xs',
};

const statusText = computed(
    () =>
        ({
            queued: 'Queued',
            processing: 'Generating',
            succeeded: 'Done',
            failed: 'Failed',
        })[props.turn.status],
);

const notes = computed(() =>
    props.turn.references.flatMap((reference, r) =>
        reference.annotations
            .map((mark, m) => ({
                key: `${reference.id}-${mark.id}`,
                label: `${r + 1}${markLetter(m)}`,
                note: mark.note.trim(),
            }))
            .filter((note) => note.note !== ''),
    ),
);

const quality = computed(
    () =>
        props.turn.quality.charAt(0).toUpperCase() +
        props.turn.quality.slice(1),
);
</script>

<template>
    <article class="flex flex-col gap-4">
        <div class="flex flex-col items-end gap-2">
            <ul v-if="turn.references.length" class="flex gap-1.5">
                <li
                    v-for="(reference, index) in turn.references"
                    :key="reference.id"
                    class="relative size-14 overflow-hidden rounded-lg border"
                >
                    <img
                        :src="reference.src"
                        :alt="`Reference ${index + 1}`"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-1 left-1 flex size-4 items-center justify-center rounded-full bg-background/90 text-[10px] font-semibold"
                        >{{ index + 1 }}</span
                    >
                </li>
            </ul>
            <div class="max-w-[80%] rounded-2xl bg-muted px-4 py-2.5 text-sm">
                <p class="whitespace-pre-wrap">{{ turn.prompt }}</p>
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
                :class="frame[turn.size]"
                role="status"
                :aria-label="`${statusText} image`"
            >
                <div
                    v-if="
                        turn.status === 'queued' || turn.status === 'processing'
                    "
                    class="absolute inset-0 bg-[linear-gradient(110deg,transparent_30%,color-mix(in_oklch,var(--foreground)_8%,transparent)_50%,transparent_70%)] bg-[length:200%_100%] motion-safe:animate-shimmer"
                />
                <div
                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm text-muted-foreground"
                >
                    <Spinner
                        v-if="
                            turn.status === 'queued' ||
                            turn.status === 'processing'
                        "
                        class="size-5"
                    />
                    <span class="font-medium text-foreground">{{
                        statusText
                    }}</span>
                    <span class="text-xs tabular-nums">
                        {{
                            turn.status === 'queued'
                                ? 'Waiting for a free slot'
                                : `${elapsed}s, usually 5 to 30s`
                        }}
                    </span>
                </div>
            </div>
            <p class="text-xs text-muted-foreground tabular-nums">
                {{ turn.size }} · {{ quality }} · Estimated
                {{ turn.estimate ? formatEstimate(turn.estimate) : '–' }}
            </p>
        </div>
    </article>
</template>
