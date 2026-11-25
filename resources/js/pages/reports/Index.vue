<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronRight, CircleAlert, CircleCheck } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/reports';
import type { ProviderUsage, SpendDay, SpendLine, SpendUser } from '@/types';

const props = defineProps<{
    from: string;
    to: string;
    days: SpendDay[];
    users: SpendUser[];
    totals: SpendLine & { pixelforge: ProviderUsage };
    pixelforge_error: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Spend report', href: index() }],
    },
});

const range = reactive({ from: props.from, to: props.to });
const open = ref(new Set<string>());

function apply(): void {
    router.get(index.url({ query: range }), {}, { preserveScroll: true });
}

function toggle(date: string): void {
    const next = new Set(open.value);

    if (!next.delete(date)) {
        next.add(date);
    }

    open.value = next;
}

/** Exact to the nanodollar, trailing zeros dropped down to cents, so it reads like Pixelforge's own "0.169080000". */
function usd(nanodollars: number): string {
    const sign = nanodollars < 0 ? '−' : '';
    const abs = Math.abs(nanodollars);
    const fraction = String(abs % 1_000_000_000)
        .padStart(9, '0')
        .replace(/0{1,7}$/, '');

    return `${sign}$${Math.floor(abs / 1_000_000_000).toLocaleString()}.${fraction.padEnd(2, '0')}`;
}

/** Our recorded cost against what Pixelforge billed: null when its usage didn't load, else the difference. */
function drift(cost: number, pixelforge: ProviderUsage): number | null {
    return pixelforge === null ? null : cost - pixelforge.cost;
}

const DATE = new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});
</script>

<template>
    <Head title="Spend report" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Spend report"
                description="What users were charged, what Pixelforge charged us, and the profit. Days are UTC and count each image on the day it finished, as Pixelforge bills it."
            />

            <form class="flex items-end gap-2" @submit.prevent="apply">
                <div class="grid gap-1">
                    <Label for="from" class="text-xs">From</Label>
                    <Input
                        id="from"
                        v-model="range.from"
                        type="date"
                        :max="range.to"
                        required
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="to" class="text-xs">To</Label>
                    <Input
                        id="to"
                        v-model="range.to"
                        type="date"
                        :min="range.from"
                        required
                    />
                </div>
                <Button type="submit" variant="secondary">Show</Button>
            </form>
        </div>

        <p
            v-if="pixelforge_error"
            class="flex items-center gap-2 rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
        >
            <CircleAlert class="size-4 shrink-0" />
            {{ pixelforge_error }} Our own numbers are below; try again to check
            them against Pixelforge.
        </p>

        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Charged to users</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ usd(totals.charged) }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Pixelforge cost</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ usd(totals.cost) }}
                </dd>
                <dd
                    v-if="drift(totals.cost, totals.pixelforge) === 0"
                    class="mt-1 flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"
                >
                    <CircleCheck class="size-3.5" /> Matches Pixelforge's usage
                </dd>
                <dd
                    v-else-if="totals.pixelforge"
                    class="mt-1 flex items-center gap-1 text-xs text-destructive"
                >
                    <CircleAlert class="size-3.5" /> Pixelforge billed
                    {{ usd(totals.pixelforge.cost) }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Profit</dt>
                <dd
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="{ 'text-destructive': totals.profit < 0 }"
                >
                    {{ usd(totals.profit) }}
                </dd>
            </div>
            <div class="rounded-xl border p-4">
                <dt class="text-sm text-muted-foreground">Images</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ totals.generations.toLocaleString() }}
                </dd>
            </div>
        </dl>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">By day</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Day</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Images
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Our cost record
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Pixelforge billed
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="day in days" :key="day.date">
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 font-medium"
                                        :aria-expanded="open.has(day.date)"
                                        :disabled="!day.users.length"
                                        @click="toggle(day.date)"
                                    >
                                        <ChevronRight
                                            class="size-4 text-muted-foreground transition-transform"
                                            :class="{
                                                'rotate-90': open.has(day.date),
                                                invisible: !day.users.length,
                                            }"
                                        />
                                        {{ DATE.format(new Date(day.date)) }}
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ day.generations }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ usd(day.charged) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ usd(day.cost) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="{
                                        'font-medium text-destructive':
                                            (drift(day.cost, day.pixelforge) ??
                                                0) !== 0,
                                    }"
                                >
                                    <template v-if="day.pixelforge">
                                        <CircleCheck
                                            v-if="
                                                drift(
                                                    day.cost,
                                                    day.pixelforge,
                                                ) === 0
                                            "
                                            class="mr-1 inline size-3.5 text-emerald-600 dark:text-emerald-400"
                                            aria-label="Matches"
                                        />
                                        <CircleAlert
                                            v-else
                                            class="mr-1 inline size-3.5"
                                            aria-label="Doesn't match"
                                        />
                                        {{ usd(day.pixelforge.cost) }}
                                    </template>
                                    <span v-else class="text-muted-foreground"
                                        >–</span
                                    >
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="{
                                        'text-destructive': day.profit < 0,
                                    }"
                                >
                                    {{ usd(day.profit) }}
                                </td>
                            </tr>
                            <template v-if="open.has(day.date)">
                                <tr
                                    v-for="user in day.users"
                                    :key="`${day.date}-${user.id}`"
                                    class="border-b bg-muted/20 text-muted-foreground"
                                >
                                    <td class="py-1.5 pr-3 pl-8">
                                        <span class="text-foreground">{{
                                            user.name
                                        }}</span>
                                        <span class="ml-1.5 text-xs">{{
                                            user.email
                                        }}</span>
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ user.generations }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ usd(user.charged) }}
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ usd(user.cost) }}
                                    </td>
                                    <td />
                                    <td
                                        class="px-3 py-1.5 text-right tabular-nums"
                                    >
                                        {{ usd(user.profit) }}
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr v-if="!days.length">
                            <td
                                colspan="6"
                                class="px-3 py-8 text-center text-muted-foreground"
                            >
                                No images finished in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="users.length" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">By user</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">User</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Images
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Charged
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Pixelforge cost
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ user.name }}</span>
                                <span
                                    class="ml-1.5 text-xs text-muted-foreground"
                                    >{{ user.email }}</span
                                >
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ user.generations }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ usd(user.charged) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ usd(user.cost) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right tabular-nums"
                                :class="{ 'text-destructive': user.profit < 0 }"
                            >
                                {{ usd(user.profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
