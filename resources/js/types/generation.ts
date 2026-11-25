export type ImageSize = '1024x1024' | '1536x1024' | '1024x1536';

export type ImageQuality = 'low' | 'medium' | 'high';

export type ReferenceImage = {
    id: number;
    src: string;
    name: string;
    /** Set for uploads, which still need storing before Pixelforge can fetch them. Null for pasted URLs. */
    file: File | null;
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
