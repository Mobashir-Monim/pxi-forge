<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { useDropZone } from '@vueuse/core';
import {
    RadioGroupItem,
    RadioGroupRoot,
    SliderRange,
    SliderRoot,
    SliderThumb,
    SliderTrack,
} from 'reka-ui';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { hasImages, onlyImages } from '@/lib/files';
import type { ImageQuality, ImageSize, ReferenceImage } from '@/types';

const props = defineProps<{
    references: ReferenceImage[];
    maxReferences: number;
    /** A file is being dragged somewhere over the browser window. */
    dragging: boolean;
}>();

const emit = defineEmits<{
    files: [files: File[]];
    url: [url: string];
    remove: [id: number];
}>();

const size = defineModel<ImageSize>('size', { required: true });
const quality = defineModel<ImageQuality>('quality', { required: true });

const sizes: {
    value: ImageSize;
    label: string;
    description: string;
    shape: string;
}[] = [
    {
        value: '1024x1024',
        label: 'Square',
        description: 'Even framing for avatars, icons and social posts.',
        shape: 'size-6',
    },
    {
        value: '1536x1024',
        label: 'Landscape',
        description: 'Wide framing for scenes, banners and wallpapers.',
        shape: 'h-5 w-7',
    },
    {
        value: '1024x1536',
        label: 'Portrait',
        description: 'Tall framing for people, posters and phone screens.',
        shape: 'h-7 w-5',
    },
];

const qualities: { value: ImageQuality; label: string }[] = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

// The slider works in steps 0..2, the draft in quality names.
const qualityStep = computed({
    get: () => [qualities.findIndex((q) => q.value === quality.value)],
    set: ([step]) => (quality.value = qualities[step].value),
});

const full = computed(() => props.references.length >= props.maxReferences);

const dropZone = ref<HTMLButtonElement>();
const { isOverDropZone } = useDropZone(dropZone, {
    dataTypes: hasImages,
    onDrop: (files) => emit('files', onlyImages(files)),
});

const fileInput = ref<HTMLInputElement>();
const url = ref('');
const urlError = ref('');

function addUrl(): void {
    try {
        const parsed = new URL(url.value.trim());

        if (!['http:', 'https:'].includes(parsed.protocol)) {
            throw new Error();
        }
    } catch {
        urlError.value = 'Enter a full http(s) image link.';

        return;
    }

    emit('url', url.value.trim());
    url.value = '';
    urlError.value = '';
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    emit('files', onlyImages(input.files));
    input.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <section>
            <h3 class="mb-2 text-sm font-medium">Size</h3>
            <RadioGroupRoot
                v-model="size"
                class="flex flex-col gap-2"
                aria-label="Size"
            >
                <RadioGroupItem
                    v-for="item in sizes"
                    :key="item.value"
                    :value="item.value"
                    class="flex h-[92px] w-full items-center gap-3 rounded-lg border px-3 text-left transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none data-[state=checked]:border-primary data-[state=checked]:bg-accent"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center"
                    >
                        <span
                            class="rounded-[3px] border-2 border-current"
                            :class="item.shape"
                        />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{
                            item.label
                        }}</span>
                        <span
                            class="block text-xs text-muted-foreground tabular-nums"
                            >{{ item.value }}</span
                        >
                        <span
                            class="mt-1 line-clamp-2 block text-xs text-muted-foreground"
                            >{{ item.description }}</span
                        >
                    </span>
                </RadioGroupItem>
            </RadioGroupRoot>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h3 id="quality-label" class="text-sm font-medium">Quality</h3>
                <span class="text-sm text-muted-foreground">{{
                    qualities[qualityStep[0]].label
                }}</span>
            </div>
            <SliderRoot
                v-model="qualityStep"
                :min="0"
                :max="qualities.length - 1"
                :step="1"
                class="relative flex h-5 w-full touch-none items-center select-none"
            >
                <SliderTrack
                    class="relative h-1.5 grow overflow-hidden rounded-full bg-muted"
                >
                    <SliderRange class="absolute h-full bg-primary" />
                </SliderTrack>
                <SliderThumb
                    aria-labelledby="quality-label"
                    :aria-valuetext="qualities[qualityStep[0]].label"
                    class="block size-4 rounded-full border-2 border-primary bg-background shadow-sm transition-[box-shadow] focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                />
            </SliderRoot>
            <p class="mt-3 text-xs text-muted-foreground">
                Higher quality takes longer and costs more.
            </p>
        </section>

        <section>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-medium">Reference images</h3>
                <span class="text-xs text-muted-foreground tabular-nums">
                    {{ props.references.length }}/{{ props.maxReferences }}
                </span>
            </div>

            <ul
                v-if="props.references.length"
                class="mb-3 grid grid-cols-2 gap-2"
            >
                <li
                    v-for="(reference, index) in props.references"
                    :key="reference.id"
                    class="group relative aspect-square animate-in overflow-hidden rounded-lg border bg-muted zoom-in-95 fade-in"
                >
                    <img
                        :src="reference.src"
                        :alt="`Reference ${index + 1}: ${reference.name}`"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-1.5 left-1.5 flex size-6 items-center justify-center rounded-full bg-background/90 text-xs font-semibold tabular-nums shadow-sm"
                    >
                        {{ index + 1 }}
                    </span>
                    <button
                        type="button"
                        class="absolute top-1.5 right-1.5 flex size-6 items-center justify-center rounded-full bg-background/90 shadow-sm transition-opacity hover:bg-background focus-visible:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                        :aria-label="`Remove reference ${index + 1}`"
                        @click="emit('remove', reference.id)"
                    >
                        <X class="size-3.5" />
                    </button>
                </li>
            </ul>

            <button
                ref="dropZone"
                type="button"
                :disabled="full"
                class="flex w-full flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed px-4 py-6 text-center transition-all duration-200 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                :class="
                    isOverDropZone
                        ? 'scale-[1.02] border-primary bg-primary/10'
                        : props.dragging
                          ? 'border-primary/60 bg-primary/5'
                          : 'hover:border-muted-foreground/40 hover:bg-accent/50'
                "
                @click="fileInput?.click()"
            >
                <ImagePlus
                    class="size-5 transition-colors"
                    :class="
                        props.dragging
                            ? 'text-primary motion-safe:animate-bounce'
                            : 'text-muted-foreground'
                    "
                />
                <span class="text-sm font-medium">
                    {{
                        full
                            ? `All ${props.maxReferences} slots used`
                            : isOverDropZone
                              ? 'Drop to add'
                              : props.dragging
                                ? 'Drop images here'
                                : 'Drop images or click to upload'
                    }}
                </span>
                <span class="text-xs text-muted-foreground">
                    {{
                        full
                            ? 'Remove one to add another.'
                            : 'Paste or drop them on the prompt too.'
                    }}
                </span>
            </button>
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                multiple
                hidden
                @change="onPick"
            />

            <template v-if="!full">
                <form class="mt-2 flex gap-2" @submit.prevent="addUrl">
                    <Input
                        v-model="url"
                        type="url"
                        placeholder="Or paste an image link"
                        aria-label="Reference image link"
                        :aria-invalid="urlError !== '' || undefined"
                        class="h-8 text-sm"
                    />
                    <Button type="submit" variant="outline" size="sm">
                        Add
                    </Button>
                </form>
                <p v-if="urlError" class="mt-1 text-xs text-destructive">
                    {{ urlError }}
                </p>
            </template>
        </section>
    </div>
</template>
