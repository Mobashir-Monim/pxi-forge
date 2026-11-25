<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

// Portrait, square, 4:3 and wide cards. Row height is fixed, only the width changes.
const widths = ['w-28 md:w-36', 'w-36 md:w-48', 'w-48 md:w-64', 'w-64 md:w-80'];
const pattern = [3, 0, 2, 1, 3, 2, 0, 1, 2];

// 48 images dealt round-robin, so every row gets its own set and a mix of styles.
// Each set is doubled so one copy is wider than large screens, otherwise the loop would show a gap.
const rows = Array.from({ length: 5 }, (_, r) => {
    const set = Array.from({ length: 48 }, (_, i) => i + 1)
        .filter((n) => n % 5 === r)
        .map((n, i) => ({
            src: `/images/auth/${n}.webp`,
            width: widths[pattern[(i + r * 2) % pattern.length]],
        }));

    return [...set, ...set];
});

// Seconds per loop for each row. Every row differs from the others by 1.3x or more, closer values read as one speed.
// Rows 0, 2, 4 move left, rows 1, 3 move right.
const durations = [30, 80, 60, 45, 100];
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-background p-6 md:p-10"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 flex items-center justify-center [perspective:1200px]"
        >
            <div
                class="flex w-[180%] shrink-0 [transform:rotateX(18deg)_rotate(-10deg)] flex-col gap-4"
            >
                <div v-for="(row, r) in rows" :key="r" class="flex">
                    <div
                        class="flex w-max shrink-0 animate-marquee gap-4 pr-4 motion-reduce:animate-none"
                        :class="{ '[animation-direction:reverse]': r % 2 }"
                        :style="{ animationDuration: `${durations[r]}s` }"
                    >
                        <img
                            v-for="(card, i) in [...row, ...row]"
                            :key="i"
                            :src="card.src"
                            alt=""
                            decoding="async"
                            class="h-36 rounded-2xl object-cover md:h-48"
                            :class="card.width"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 bg-background/60 bg-[radial-gradient(ellipse_at_center,transparent_0%,var(--background)_80%)]"
        />

        <div
            class="relative w-full max-w-md rounded-2xl border bg-background/80 p-8 shadow-2xl backdrop-blur-xl"
        >
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <AppLogoIcon
                            class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                        />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
