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
