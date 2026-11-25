<script setup lang="ts">
import { SquareDashedMousePointer, Trash2 } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { markLetter } from '@/lib/prompt';
import type { Annotation } from '@/types';

/**
 * Shows an image large and lets the user drag boxes on it, each with a note for the generation. Works on any image,
 * a reference or a finished generation.
 */
const props = defineProps<{
    src: string;
    title: string;
    /** The mark whose note gets focus when the modal opens. */
    focusId?: number | null;
}>();

const open = defineModel<boolean>('open', { required: true });
const marks = defineModel<Annotation[]>('annotations', { required: true });

/** Boxes smaller than this (as a fraction of the image) are treated as a stray click, not a mark. */
const MIN_SIZE = 0.02;

const surface = ref<HTMLElement>();
const active = ref<number | null>(null);
let drawing: { id: number; x: number; y: number } | null = null;

const clamp = (n: number) => Math.min(1, Math.max(0, n));

function point(event: PointerEvent): { x: number; y: number } {
    const rect = surface.value!.getBoundingClientRect();

    return {
        x: clamp((event.clientX - rect.left) / rect.width),
        y: clamp((event.clientY - rect.top) / rect.height),
    };
}

function find(id: number): Annotation | undefined {
    return marks.value.find((mark) => mark.id === id);
}

function start(event: PointerEvent): void {
    if (event.button !== 0) {
        return;
    }

    surface.value!.setPointerCapture(event.pointerId);
    const { x, y } = point(event);
    const id = Math.max(0, ...marks.value.map((mark) => mark.id)) + 1;

    marks.value = [...marks.value, { id, x, y, width: 0, height: 0, note: '' }];
    drawing = { id, x, y };
    active.value = id;
}

function move(event: PointerEvent): void {
    const mark = drawing && find(drawing.id);

    if (!drawing || !mark) {
        return;
    }

    const { x, y } = point(event);
    mark.x = Math.min(drawing.x, x);
    mark.y = Math.min(drawing.y, y);
    mark.width = Math.abs(x - drawing.x);
    mark.height = Math.abs(y - drawing.y);
}

function end(): void {
    const mark = drawing && find(drawing.id);
    drawing = null;

    if (!mark) {
        return;
    }

    if (mark.width < MIN_SIZE || mark.height < MIN_SIZE) {
        remove(mark.id);

        return;
    }

    focusNote(mark.id);
}

function remove(id: number): void {
    marks.value = marks.value.filter((mark) => mark.id !== id);

    if (active.value === id) {
        active.value = null;
    }
}

function focusNote(id: number): void {
    active.value = id;
    nextTick(() =>
        document
            .querySelector<HTMLTextAreaElement>(`[data-mark-note="${id}"]`)
            ?.focus(),
    );
}

/** Opening from a note lands in that note, instead of the dialog's default first field. */
function onOpenFocus(event: Event): void {
    if (props.focusId && find(props.focusId)) {
        event.preventDefault();
        focusNote(props.focusId);
    }
}

watch(open, (isOpen) => {
    if (!isOpen) {
        active.value = null;
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="flex max-h-[92vh] flex-col overflow-hidden sm:max-w-5xl"
            @open-auto-focus="onOpenFocus"
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    Drag on the image to mark an area, then say what should
                    change there. Notes are added to your prompt.
                </DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 flex-col gap-4 md:flex-row">
                <div
                    class="flex min-h-0 min-w-0 flex-1 items-center justify-center rounded-lg bg-muted/40 p-2"
                >
                    <div
                        ref="surface"
                        class="relative cursor-crosshair touch-none select-none"
                        @pointerdown="start"
                        @pointermove="move"
                        @pointerup="end"
                        @pointercancel="end"
                    >
                        <img
                            :src="src"
                            :alt="title"
                            draggable="false"
                            class="block max-h-[40vh] max-w-full rounded md:max-h-[65vh]"
                        />
                        <button
                            v-for="(mark, index) in marks"
                            :key="mark.id"
                            type="button"
                            class="absolute border-2 transition-colors"
                            :class="
                                active === mark.id
                                    ? 'border-amber-300 bg-amber-300/20'
                                    : 'border-sky-400 bg-sky-400/10 hover:bg-sky-400/20'
                            "
                            :style="{
                                left: `${mark.x * 100}%`,
                                top: `${mark.y * 100}%`,
                                width: `${mark.width * 100}%`,
                                height: `${mark.height * 100}%`,
                            }"
                            :aria-label="`Area ${markLetter(index)}`"
                            @pointerdown.stop
                            @click="focusNote(mark.id)"
                        >
                            <span
                                class="absolute top-0 left-0 flex size-5 items-center justify-center text-[11px] font-semibold text-black"
                                :class="
                                    active === mark.id
                                        ? 'bg-amber-300'
                                        : 'bg-sky-400'
                                "
                            >
                                {{ markLetter(index) }}
                            </span>
                        </button>
                    </div>
                </div>

                <aside
                    class="flex min-h-0 flex-col gap-2 overflow-y-auto md:w-72 md:shrink-0"
                    aria-label="Marked areas"
                >
                    <div
                        v-if="marks.length === 0"
                        class="flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        <SquareDashedMousePointer class="size-5" />
                        No marked areas yet.
                    </div>

                    <div
                        v-for="(mark, index) in marks"
                        :key="mark.id"
                        class="rounded-lg border p-2 transition-colors"
                        :class="active === mark.id ? 'border-amber-300/60' : ''"
                        @mouseenter="active = mark.id"
                    >
                        <div class="mb-1.5 flex items-center justify-between">
                            <span
                                class="flex size-5 items-center justify-center rounded-sm text-[11px] font-semibold text-black"
                                :class="
                                    active === mark.id
                                        ? 'bg-amber-300'
                                        : 'bg-sky-400'
                                "
                            >
                                {{ markLetter(index) }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-7"
                                :aria-label="`Delete area ${markLetter(index)}`"
                                @click="remove(mark.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                        <textarea
                            v-model="mark.note"
                            :data-mark-note="mark.id"
                            rows="2"
                            placeholder="What should change here?"
                            :aria-label="`Note for area ${markLetter(index)}`"
                            class="w-full resize-none rounded-md border bg-transparent px-2 py-1.5 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring"
                            @focus="active = mark.id"
                        />
                        <p
                            v-if="mark.note.trim() === ''"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Without a note, this area isn't sent.
                        </p>
                    </div>
                </aside>
            </div>

            <DialogFooter>
                <Button type="button" @click="open = false">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
