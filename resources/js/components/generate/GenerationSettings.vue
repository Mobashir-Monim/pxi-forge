<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ImageQuality, ImageSize, ReferenceImage } from '@/types';

const props = defineProps<{
    references: ReferenceImage[];
    maxReferences: number;
}>();

const emit = defineEmits<{
    files: [files: File[]];
    url: [url: string];
    remove: [id: number];
}>();

const size = defineModel<ImageSize>('size', { required: true });
const quality = defineModel<ImageQuality>('quality', { required: true });

const sizes: { value: ImageSize; label: string; shape: string }[] = [
    { value: '1024x1024', label: 'Square', shape: 'w-5 h-5' },
    { value: '1536x1024', label: 'Landscape', shape: 'w-6 h-4' },
    { value: '1024x1536', label: 'Portrait', shape: 'w-4 h-6' },
];

const qualities: { value: ImageQuality; label: string }[] = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

const optionClass =
    'rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none data-[state=checked]:border-primary data-[state=checked]:bg-accent';

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
    emit('files', Array.from(input.files ?? []));
    input.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <section>
            <h3 class="mb-2 text-sm font-medium">Size</h3>
            <RadioGroupRoot
                v-model="size"
                class="grid grid-cols-3 gap-2"
                aria-label="Size"
            >
                <RadioGroupItem
                    v-for="item in sizes"
                    :key="item.value"
                    :value="item.value"
                    :class="[optionClass, 'flex flex-col items-center gap-1.5']"
                >
                    <span class="flex h-6 items-center">
                        <span
                            class="rounded-[3px] border-2 border-current"
                            :class="item.shape"
                        />
                    </span>
                    <span>{{ item.label }}</span>
                    <span class="text-[11px] text-muted-foreground">{{
                        item.value
                    }}</span>
                </RadioGroupItem>
            </RadioGroupRoot>
        </section>

        <section>
            <h3 class="mb-2 text-sm font-medium">Quality</h3>
            <RadioGroupRoot
                v-model="quality"
                class="grid grid-cols-3 gap-2"
                aria-label="Quality"
            >
                <RadioGroupItem
                    v-for="item in qualities"
                    :key="item.value"
                    :value="item.value"
                    :class="optionClass"
                >
                    {{ item.label }}
                </RadioGroupItem>
            </RadioGroupRoot>
            <p class="mt-2 text-xs text-muted-foreground">
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
                    class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
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
            <p v-else class="mb-3 text-xs text-muted-foreground">
                Add up to {{ props.maxReferences }} images to guide the style.
                Mention them in your prompt by number, like "colors from 1".
                Each one adds a little to the cost.
            </p>

            <template v-if="props.references.length < props.maxReferences">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="w-full"
                    @click="fileInput?.click()"
                >
                    <ImagePlus /> Upload images
                </Button>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    hidden
                    @change="onPick"
                />

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
