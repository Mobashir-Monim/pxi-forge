/** The signed-in user's credits in nanodollars ($0.000000001). Running images hold their estimate's upper bound. */
export type Credits = {
    balance: number;
    held: number;
    available: number;
    /** Packs on offer, in US cents. */
    packs: number[];
};

export type SpendLine = {
    generations: number;
    charged: number;
    cost: number;
    profit: number;
};

export type SpendUser = SpendLine & { id: string; name: string; email: string };

/** What Pixelforge's GET /v1/usage billed, null when it couldn't be loaded. */
export type ProviderUsage = { generations: number; cost: number } | null;

export type SpendDay = SpendLine & {
    date: string;
    pixelforge: ProviderUsage;
    users: SpendUser[];
};
