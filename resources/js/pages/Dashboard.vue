<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useDropZone } from '@vueuse/core';
import { onBeforeUnmount, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import GenerationSettings from '@/components/generate/GenerationSettings.vue';
import PromptBar from '@/components/generate/PromptBar.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCostEstimate } from '@/composables/useCostEstimate';
import { hasImages } from '@/lib/files';
import { dashboard } from '@/routes';
import type { GenerationDraft, ReferenceImage } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'New image', href: dashboard() }],
    },
});

/** Pixelforge's limit per generation. */
const MAX_REFERENCES = 4;

const draft = reactive<GenerationDraft>({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    references: [],
});

const settingsOpen = ref(false);
const { estimate, loading } = useCostEstimate(draft);

// Lights up every drop zone while an image is dragged anywhere over the page, and stops a drop that misses them from
// opening the file in place of the app.
const { isOverDropZone: draggingFile } = useDropZone(
    () => (typeof document === 'undefined' ? null : document.documentElement),
    { dataTypes: hasImages, preventDefaultForUnhandled: true },
);

let nextId = 1;

function addReference(reference: Omit<ReferenceImage, 'id'>): boolean {
    if (draft.references.length >= MAX_REFERENCES) {
        return false;
    }

    draft.references.push({ id: nextId++, ...reference });

    return true;
}

function addFiles(files: File[]): void {
    const added = files.filter((file) =>
        addReference({
            src: URL.createObjectURL(file),
            name: file.name,
            file,
        }),
    );

    if (added.length < files.length) {
        toast.error(
            `You can use up to ${MAX_REFERENCES} reference images, so ${files.length - added.length} weren't added.`,
        );
    }
}

function addUrl(url: string): void {
    addReference({ src: url, name: url, file: null });
}

function revoke(reference: ReferenceImage): void {
    if (reference.file) {
        URL.revokeObjectURL(reference.src);
    }
}

function removeReference(id: number): void {
    const index = draft.references.findIndex((r) => r.id === id);
    revoke(draft.references[index]);
    draft.references.splice(index, 1);
}

onBeforeUnmount(() => draft.references.forEach(revoke));

function submit(): void {
    // ponytail: placeholder until generations are stored and sent to Pixelforge.
    toast.info('Generating comes next. Your settings are ready.');
}
</script>

<template>
    <Head title="New image" />

    <div class="flex min-h-0 flex-1">
        <main
            class="flex min-w-0 flex-1 flex-col items-center justify-center gap-8 p-4 pb-8"
        >
            <h1 class="text-center text-2xl font-medium tracking-tight">
                What do you want to create?
            </h1>
            <div class="w-full max-w-3xl">
                <PromptBar
                    v-model="draft.prompt"
                    :estimate="estimate"
                    :loading="loading"
                    :reference-count="draft.references.length"
                    :max-references="MAX_REFERENCES"
                    :dragging="draggingFile"
                    @files="addFiles"
                    @submit="submit"
                    @open-settings="settingsOpen = true"
                />
            </div>
        </main>

        <aside
            class="hidden w-80 shrink-0 overflow-y-auto border-l border-sidebar-border/70 p-5 lg:block"
            aria-label="Image settings"
        >
            <GenerationSettings
                v-model:size="draft.size"
                v-model:quality="draft.quality"
                :references="draft.references"
                :max-references="MAX_REFERENCES"
                :dragging="draggingFile"
                @files="addFiles"
                @url="addUrl"
                @remove="removeReference"
            />
        </aside>

        <Sheet v-model:open="settingsOpen">
            <SheetContent class="overflow-y-auto lg:hidden">
                <SheetHeader>
                    <SheetTitle>Image settings</SheetTitle>
                    <SheetDescription>
                        Size, quality and reference images.
                    </SheetDescription>
                </SheetHeader>
                <div class="px-4 pb-4">
                    <GenerationSettings
                        v-model:size="draft.size"
                        v-model:quality="draft.quality"
                        :references="draft.references"
                        :max-references="MAX_REFERENCES"
                        :dragging="draggingFile"
                        @files="addFiles"
                        @url="addUrl"
                        @remove="removeReference"
                    />
                </div>
            </SheetContent>
        </Sheet>
    </div>
</template>
