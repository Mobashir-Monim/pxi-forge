<script setup lang="ts">
import { ArrowUp, ImagePlus, SlidersHorizontal } from '@lucide/vue';
import { useTextareaAutosize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatEstimate } from '@/composables/useCostEstimate';
import type { CostEstimate } from '@/types';

const props = defineProps<{
    estimate: CostEstimate | null;
    loading: boolean;
    referenceCount: number;
    maxReferences: number;
}>();

const emit = defineEmits<{
    submit: [];
    files: [files: File[]];
    openSettings: [];
}>();

const prompt = defineModel<string>({ required: true });

const { textarea } = useTextareaAutosize({ input: prompt });
const fileInput = ref<HTMLInputElement>();

const canSubmit = computed(() => prompt.value.trim() !== '');

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

function images(list: FileList | null | undefined): File[] {
    return Array.from(list ?? []).filter((file) =>
        file.type.startsWith('image/'),
    );
}

function onPaste(event: ClipboardEvent): void {
    const files = images(event.clipboardData?.files);

    if (files.length > 0) {
        event.preventDefault();
        emit('files', files);
    }
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    emit('files', images(input.files));
    input.value = '';
}
</script>

<template>
    <div class="w-full">
        <form
            class="rounded-3xl border bg-card p-3 shadow-sm transition-colors focus-within:border-ring"
            @submit.prevent="submit"
            @dragover.prevent
            @drop.prevent="emit('files', images($event.dataTransfer?.files))"
        >
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
