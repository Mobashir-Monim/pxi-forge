export type ImageSize = '1024x1024' | '1536x1024' | '1024x1536';

export type ImageQuality = 'low' | 'medium' | 'high';

/**
 * A box marked on an image with a note for the generation. Position and size are fractions (0..1) of the image, so
 * they hold at any display size. Its letter (A, B, ...) comes from its place in the list.
 */
export type Annotation = {
    id: number;
    x: number;
    y: number;
    width: number;
    height: number;
    note: string;
};

export type ReferenceImage = {
    id: number;
    src: string;
    name: string;
    /** Set for uploads, which still need storing before Pixelforge can fetch them. Null for pasted URLs. */
    file: File | null;
    annotations: Annotation[];
};

export type GenerationDraft = {
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    references: ReferenceImage[];
};

/** The user's price range in nanodollars ($0.000000001), markup included. */
export type CostEstimate = {
    min: number;
    max: number;
};

/** One mark as the prompt bar lists it, labelled like "1A" (reference 1, area A). */
export type NoteSummary = {
    referenceId: number;
    markId: number;
    label: string;
    note: string;
};

export type GenerationStatus =
    | 'pending'
    | 'queued'
    | 'processing'
    | 'succeeded'
    | 'failed';

/** A reference as a sent generation keeps it: a stored URL and its notes, read-only. */
export type SentReference = {
    url: string;
    name: string;
    annotations: Omit<Annotation, 'id'>[];
};

/** One generation in a chat's thread, as the server sends it. Money is nanodollars, the user's price only. */
export type Generation = {
    id: string;
    status: GenerationStatus;
    /** What the user typed. */
    message: string;
    /** What Pixelforge received: the message with every note appended. */
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    references: SentReference[];
    estimate: CostEstimate;
    /** What the user was charged, set once it's finished. Zero when failed or late. */
    charge: number | null;
    /** While pending: why it hasn't reached Pixelforge yet. */
    waiting_reason: 'busy' | 'overloaded' | null;
    failure: { reason: string; message: string; retryable: boolean } | null;
    /** Finished after we stopped waiting, so it's free. */
    late: boolean;
    image_url: string | null;
    retry_of_id: string | null;
    created_at: string;
    /** When Pixelforge accepted it. Timings count from here. */
    accepted_at: string | null;
    completed_at: string | null;
};

export type ChatSummary = {
    id: string;
    title: string;
    archived: boolean;
};
