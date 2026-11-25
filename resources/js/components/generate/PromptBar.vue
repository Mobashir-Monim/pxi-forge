<script setup lang="ts">
import { ArrowUp, ImagePlus, SlidersHorizontal, X } from '@lucide/vue';
import { useDropZone, useTextareaAutosize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatEstimate } from '@/composables/useCostEstimate';
import { hasImages, onlyImages } from '@/lib/files';
import { PROMPT_LIMIT } from '@/lib/prompt';
import type { CostEstimate, NoteSummary } from '@/types';

const props = defineProps<{
    estimate: CostEstimate | null;
    loading: boolean;
    referenceCount: number;
    maxReferences: number;
    /** A file is being dragged somewhere over the browser window. */
    dragging: boolean;
    notes: NoteSummary[];
    /** How many characters the prompt plus notes run past Pixelforge's limit, zero or less when within it. */
    overLimit: number;
}>();

const emit = defineEmits<{
    submit: [];
    files: [files: File[]];
    openSettings: [];
    openNote: [note: NoteSummary];
    removeNote: [note: NoteSummary];
}>();

const prompt = defineModel<string>({ required: true });

const { textarea } = useTextareaAutosize({ input: prompt });
const fileInput = ref<HTMLInputElement>();

const form = ref<HTMLFormElement>();
const { isOverDropZone } = useDropZone(form, {
    dataTypes: hasImages,
    onDrop: (files) => emit('files', onlyImages(files)),
});

const canSubmit = computed(
    () => prompt.value.trim() !== '' && props.overLimit <= 0,
);

function submit(): void {
    if (canSubmit.value) {
        emit('submit');
    }
}

function onEnter(event: KeyboardEvent): void {
    // Enter while an IME is composing picks a candidate, it shouldn't send.
    if (!event.isComposing) {
        event.preventDefault();
        submit();
    }
}

function onPaste(event: ClipboardEvent): void {
    const files = onlyImages(event.clipboardData?.files);

    if (files.length > 0) {
        event.preventDefault();
        emit('files', files);
    }
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    emit('files', onlyImages(input.files));
    input.value = '';
}
</script>

<template>
    <div class="w-full">
        <form
            ref="form"
            class="relative rounded-3xl border bg-card p-3 shadow-sm transition-all duration-200 focus-within:border-ring"
            :class="{
                'border-dashed border-primary/60': props.dragging,
                'scale-[1.01] border-primary': isOverDropZone,
            }"
            @submit.prevent="submit"
        >
            <div
                class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center gap-2 rounded-3xl text-sm font-medium transition-opacity duration-200"
                :class="[
                    props.dragging ? 'opacity-100' : 'opacity-0',
                    isOverDropZone ? 'bg-card/95 text-primary' : 'bg-card/90',
                ]"
                aria-hidden="true"
            >
                <ImagePlus
                    class="size-5 text-primary motion-safe:animate-bounce"
                />
                {{
                    isOverDropZone
                        ? 'Drop to add as references'
                        : 'Drop images here'
                }}
            </div>
            <ul
                v-if="props.notes.length"
                class="mb-1 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto px-1 pt-1"
                aria-label="Notes on reference images"
            >
                <li
                    v-for="note in props.notes"
                    :key="`${note.referenceId}-${note.markId}`"
                    class="flex max-w-full animate-in items-center rounded-full border bg-muted/50 text-xs zoom-in-95 fade-in"
                >
                    <button
                        type="button"
                        class="flex min-w-0 items-center gap-1.5 rounded-l-full py-1 pr-1 pl-2.5 hover:text-foreground"
                        :title="note.note || 'No note yet'"
                        @click="emit('openNote', note)"
                    >
                        <span class="font-semibold tabular-nums">{{
                            note.label
                        }}</span>
                        <span
                            class="max-w-48 truncate"
                            :class="
                                note.note
                                    ? 'text-muted-foreground'
                                    : 'text-muted-foreground/60 italic'
                            "
                            >{{ note.note || 'No note yet' }}</span
                        >
                    </button>
                    <button
                        type="button"
                        class="mr-1 flex size-5 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
                        :aria-label="`Remove note ${note.label}`"
                        @click="emit('removeNote', note)"
                    >
                        <X class="size-3" />
                    </button>
                </li>
            </ul>
            <textarea
                ref="textarea"
                v-model="prompt"
                rows="1"
                maxlength="4000"
                placeholder="Describe the image you want"
                aria-label="Prompt"
                class="max-h-60 w-full resize-none bg-transparent px-2 py-1.5 text-base outline-none placeholder:text-muted-foreground"
                @keydown.enter.exact="onEnter"
                @paste="onPaste"
            />

            <div class="mt-1 flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="rounded-full"
                    aria-label="Add reference images"
                    :disabled="referenceCount >= maxReferences"
                    @click="fileInput?.click()"
                >
                    <ImagePlus />
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    hidden
                    @change="onPick"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="rounded-full lg:hidden"
                    aria-label="Image settings"
                    @click="emit('openSettings')"
                >
                    <SlidersHorizontal />
                </Button>
                <span
                    v-if="referenceCount > 0"
                    class="px-1 text-xs text-muted-foreground"
                >
                    {{ referenceCount }}/{{ maxReferences }} references
                </span>

                <Button
                    type="submit"
                    size="icon-sm"
                    class="ml-auto rounded-full"
                    aria-label="Generate"
                    :disabled="!canSubmit"
                >
                    <ArrowUp />
                </Button>
            </div>
        </form>

        <p
            v-if="props.overLimit > 0"
            class="mt-2 text-center text-xs text-destructive"
            aria-live="polite"
        >
            Your prompt and notes are
            {{ props.overLimit.toLocaleString() }} characters over the
            {{ PROMPT_LIMIT.toLocaleString() }} limit. Shorten them to generate.
        </p>
        <p
            v-else
            class="mt-2 text-center text-xs text-muted-foreground"
            aria-live="polite"
        >
            Estimated cost
            <span
                class="font-medium text-foreground tabular-nums transition-opacity"
                :class="{ 'opacity-50': props.loading }"
            >
                {{
                    props.estimate ? formatEstimate(props.estimate) : '–'
                }} </span
            >. You're only charged if the image is made.
        </p>
    </div>
</template>
