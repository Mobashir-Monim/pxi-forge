<script setup lang="ts">
import { siFacebook, siGoogle, siInstagram, siTiktok } from 'simple-icons';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

// Placeholders until social sign-in is built, none of these are wired to a provider.
const providers = [
    { name: 'Google', icons: [siGoogle] },
    { name: 'Facebook / Instagram', icons: [siFacebook, siInstagram] },
    { name: 'TikTok', icons: [siTiktok] },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex items-center gap-3 text-xs text-muted-foreground uppercase"
        >
            <span class="h-px flex-1 bg-border" />
            or continue with
            <span class="h-px flex-1 bg-border" />
        </div>

        <TooltipProvider :delay-duration="0">
            <div class="grid grid-cols-3 gap-2">
                <Tooltip v-for="provider in providers" :key="provider.name">
                    <TooltipTrigger as-child>
                        <!-- The span keeps the tooltip working, a disabled button gets no pointer events. -->
                        <span
                            tabindex="0"
                            class="cursor-not-allowed rounded-md"
                        >
                            <button
                                type="button"
                                disabled
                                :aria-label="`${provider.name}, coming soon`"
                                class="flex h-10 w-full items-center justify-center gap-2 rounded-md border bg-background/60 opacity-60"
                            >
                                <svg
                                    v-for="icon in provider.icons"
                                    :key="icon.slug"
                                    role="img"
                                    viewBox="0 0 24 24"
                                    class="size-4 fill-current"
                                    aria-hidden="true"
                                >
                                    <path :d="icon.path" />
                                </svg>
                            </button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent
                        >{{ provider.name }}: coming soon</TooltipContent
                    >
                </Tooltip>
            </div>
        </TooltipProvider>
    </div>
</template>
