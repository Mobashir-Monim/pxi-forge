<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useBuyCredits } from '@/composables/useBuyCredits';
import { formatCredits } from '@/composables/useCostEstimate';
import { ulid } from '@/lib/ulid';
import { checkout } from '@/routes/credits';

const page = usePage();
const credits = computed(() => page.props.credits);
const { open } = useBuyCredits();

// One id per opening, so a double click on a pack makes one purchase.
const clickId = ref(ulid());
const buying = ref<number | null>(null);

watch(open, (isOpen) => {
    if (isOpen) {
        clickId.value = ulid();
        buying.value = null;
    }
});

function buy(cents: number): void {
    if (buying.value !== null) {
        return;
    }

    buying.value = cents;
    // The server answers with the checkout's address, and Inertia leaves the app for it.
    router.post(
        checkout.url(),
        { client_request_id: clickId.value, amount_cents: cents },
        { onFinish: () => (buying.value = null) },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="credits" class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Buy credits</DialogTitle>
                <DialogDescription>
                    You have
                    <span class="font-medium text-foreground tabular-nums">{{
                        formatCredits(credits.available)
                    }}</span>
                    to spend<template v-if="credits.held > 0">
                        , plus
                        <span class="tabular-nums">{{
                            formatCredits(credits.held)
                        }}</span>
                        held for images still generating</template
                    >. Credits are US dollars and never expire.
                </DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-3 gap-2">
                <Button
                    v-for="cents in credits.packs"
                    :key="cents"
                    variant="outline"
                    class="h-16 text-lg font-semibold tabular-nums"
                    :disabled="buying !== null"
                    @click="buy(cents)"
                >
                    <Spinner v-if="buying === cents" />
                    <template v-else>${{ cents / 100 }}</template>
                </Button>
            </div>

            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <CreditCard class="mt-px size-3.5 shrink-0" />
                You'll pay on Stripe's secure checkout. It's in test mode, so
                use card 4242 4242 4242 4242 with any future date and CVC.
                Nothing is charged.
            </p>
        </DialogContent>
    </Dialog>
</template>
