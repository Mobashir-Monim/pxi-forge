<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { useDropZone } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AnnotateImage from '@/components/generate/AnnotateImage.vue';
import GenerationTurn from '@/components/generate/GenerationTurn.vue';
import GenerationSettings from '@/components/generate/GenerationSettings.vue';
import PromptBar from '@/components/generate/PromptBar.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCan } from '@/composables/useCan';
import { useCostEstimate } from '@/composables/useCostEstimate';
import { hasImages } from '@/lib/files';
import {
    characterCount,
    composePrompt,
    markLetter,
    PROMPT_LIMIT,
} from '@/lib/prompt';
import { ulid } from '@/lib/ulid';
import { dashboard } from '@/routes';
import { store as sendInChat } from '@/routes/chats/generations';
import {
    retry as retryRoute,
    store as sendInNewChat,
} from '@/routes/generations';
import { store as uploadRoute } from '@/routes/references';
import type {
    ChatSummary,
    Generation,
    GenerationDraft,
    NoteSummary,
    ReferenceImage,
} from '@/types';

const props = defineProps<{
    /** Null on the new-chat page. */
    chat?: ChatSummary | null;
    generations?: Generation[];
}>();

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
// What Pixelforge will receive: the prompt with every note appended. The estimate and the limit both use it.
const composedPrompt = computed(() =>
    composePrompt(draft.prompt, draft.references),
);
const overLimit = computed(
    () => characterCount(composedPrompt.value) - PROMPT_LIMIT,
);

const { estimate, loading } = useCostEstimate(() => ({
    prompt: composedPrompt.value,
    size: draft.size,
    quality: draft.quality,
    referenceImages: draft.references.length,
}));

const notes = computed<NoteSummary[]>(() =>
    draft.references.flatMap((reference, r) =>
        reference.annotations.map((mark, m) => ({
            referenceId: reference.id,
            markId: mark.id,
            label: `${r + 1}${markLetter(m)}`,
            note: mark.note.trim(),
        })),
    ),
);

/** The reference open in the annotator, and the mark to focus when it opens from a note. */
const annotating = ref<{ referenceId: number; focusId: number | null } | null>(
    null,
);
const annotatingReference = computed(() =>
    draft.references.find((r) => r.id === annotating.value?.referenceId),
);
const annotatingIndex = computed(() =>
    draft.references.findIndex((r) => r.id === annotating.value?.referenceId),
);

function annotate(referenceId: number, focusId: number | null = null): void {
    settingsOpen.value = false;
    annotating.value = { referenceId, focusId };
}

function removeNote(note: NoteSummary): void {
    const reference = draft.references.find((r) => r.id === note.referenceId);

    if (reference) {
        reference.annotations = reference.annotations.filter(
            (mark) => mark.id !== note.markId,
        );
    }
}

// Lights up every drop zone while an image is dragged anywhere over the page, and stops a drop that misses them from
// opening the file in place of the app.
const { isOverDropZone: draggingFile } = useDropZone(
    () => (typeof document === 'undefined' ? null : document.documentElement),
    { dataTypes: hasImages, preventDefaultForUnhandled: true },
);

let nextId = 1;

function addReference(
    reference: Omit<ReferenceImage, 'id' | 'annotations'>,
): boolean {
    if (draft.references.length >= MAX_REFERENCES) {
        return false;
    }

    draft.references.push({ id: nextId++, annotations: [], ...reference });

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

const can = useCan();
const turns = computed(() => props.generations ?? []);
const thread = ref<HTMLElement>();

const running = computed(() =>
    turns.value.some((turn) =>
        ['pending', 'queued', 'processing'].includes(turn.status),
    ),
);
const archived = computed(() => props.chat?.archived ?? false);

/** One running image per chat, and a closed chat takes nothing. The server enforces both, this explains them. */
const locked = computed(() => {
    if (archived.value) {
        return 'A prompt in this chat was blocked, so the chat is closed. Start a new chat to keep going.';
    }

    if (!can('generations.create')) {
        return "You don't have permission to generate images.";
    }

    return running.value
        ? 'Wait for this image to finish before sending the next prompt.'
        : null;
});

// Webhooks update the server. The page polls only the thread, and only while something is running.
const { start, stop } = usePoll(
    2000,
    { only: ['chat', 'generations', 'chats'] },
    { autoStart: false },
);
watch(running, (now) => (now ? start() : stop()), { immediate: true });

function scrollToEnd(): void {
    nextTick(() =>
        thread.value?.lastElementChild?.scrollIntoView({ behavior: 'smooth' }),
    );
}

watch(() => turns.value.length, scrollToEnd);

// Switching chats starts a clean draft, so references never leak from one chat into another.
watch(
    () => props.chat?.id,
    () => {
        draft.references.forEach(revoke);
        draft.prompt = '';
        draft.references = [];
    },
);

onBeforeUnmount(() => draft.references.forEach(revoke));

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Pixelforge takes references as public URLs, so uploads are stored first. */
async function upload(file: File): Promise<{ url: string; name: string }> {
    const body = new FormData();
    body.append('file', file);

    const response = await fetch(uploadRoute.url(), {
        method: 'POST',
        body,
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    });

    if (!response.ok) {
        throw new Error(`Couldn't upload ${file.name}.`);
    }

    return response.json();
}

const sending = ref(false);
// Kept until the send succeeds, so sending again after a dropped response is recognised by the server, not doubled.
let requestId = ulid();

async function submit(): Promise<void> {
    if (sending.value || locked.value) {
        return;
    }

    sending.value = true;

    let references;

    try {
        references = await Promise.all(
            draft.references.map(async (reference) => ({
                ...(reference.file
                    ? await upload(reference.file)
                    : { url: reference.src, name: reference.name }),
                annotations: reference.annotations.map(
                    ({ x, y, width, height, note }) => ({
                        x,
                        y,
                        width,
                        height,
                        note,
                    }),
                ),
            })),
        );
    } catch (error) {
        toast.error((error as Error).message);
        sending.value = false;

        return;
    }

    router.post(
        props.chat ? sendInChat.url(props.chat.id) : sendInNewChat.url(),
        {
            client_request_id: requestId,
            message: draft.prompt.trim(),
            prompt: composedPrompt.value,
            size: draft.size,
            quality: draft.quality,
            references,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                requestId = ulid();
                draft.references.forEach(revoke);
                draft.prompt = '';
                draft.references = [];
            },
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? "Couldn't send that."),
            onFinish: () => (sending.value = false),
        },
    );
}

const retrying = ref(false);

function retry(generation: Generation): void {
    router.post(
        retryRoute.url(generation.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (retrying.value = true),
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? "Couldn't retry that."),
            onFinish: () => (retrying.value = false),
        },
    );
}

function canRetry(generation: Generation, index: number): boolean {
    return (
        index === turns.value.length - 1 &&
        generation.status === 'failed' &&
        (generation.failure?.retryable ?? false) &&
        !archived.value &&
        can('generations.retry')
    );
}

function reuse(url: string, name: string): void {
    if (!addReference({ src: url, name, file: null })) {
        toast.error(`You can use up to ${MAX_REFERENCES} reference images.`);
    }
}
</script>

<template>
    <Head :title="chat?.title ?? 'New image'" />

    <div class="flex min-h-0 flex-1">
        <main
            class="flex min-w-0 flex-1 flex-col items-center gap-8 px-4"
            :class="turns.length ? '' : 'justify-center pb-8'"
        >
            <h1
                v-if="!turns.length"
                class="text-center text-2xl font-medium tracking-tight"
            >
                What do you want to create?
            </h1>
            <div
                v-else
                ref="thread"
                class="flex w-full max-w-3xl flex-1 flex-col gap-10 pt-4"
            >
                <GenerationTurn
                    v-for="(turn, index) in turns"
                    :key="turn.id"
                    :generation="turn"
                    :can-retry="canRetry(turn, index)"
                    :can-reuse="!archived"
                    :retrying="retrying"
                    @retry="retry(turn)"
                    @reuse="reuse"
                />
            </div>
            <div
                class="w-full max-w-3xl"
                :class="
                    turns.length
                        ? 'sticky bottom-0 bg-gradient-to-t from-background from-70% to-transparent pt-6 pb-4'
                        : ''
                "
            >
                <PromptBar
                    v-model="draft.prompt"
                    :estimate="estimate"
                    :loading="loading"
                    :reference-count="draft.references.length"
                    :max-references="MAX_REFERENCES"
                    :dragging="draggingFile"
                    :notes="notes"
                    :over-limit="overLimit"
                    :locked="locked"
                    :sending="sending"
                    @files="addFiles"
                    @open-note="
                        (note) => annotate(note.referenceId, note.markId)
                    "
                    @remove-note="removeNote"
                    @submit="submit"
                    @open-settings="settingsOpen = true"
                />
            </div>
        </main>

        <aside
            class="hidden w-80 shrink-0 border-l border-sidebar-border/70 lg:block"
            aria-label="Image settings"
        >
            <div class="sticky top-0 max-h-svh overflow-y-auto p-5">
                <GenerationSettings
                    v-model:size="draft.size"
                    v-model:quality="draft.quality"
                    :references="draft.references"
                    :max-references="MAX_REFERENCES"
                    :dragging="draggingFile"
                    @files="addFiles"
                    @url="addUrl"
                    @remove="removeReference"
                    @annotate="annotate"
                />
            </div>
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
                        @annotate="annotate"
                    />
                </div>
            </SheetContent>
        </Sheet>

        <AnnotateImage
            v-if="annotatingReference"
            :open="annotating !== null"
            :src="annotatingReference.src"
            :title="`Reference ${annotatingIndex + 1}`"
            :focus-id="annotating?.focusId"
            v-model:annotations="annotatingReference.annotations"
            @update:open="(open) => !open && (annotating = null)"
        />
    </div>
</template>
