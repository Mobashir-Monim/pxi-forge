<?php

namespace App\Domains\Billing\Actions\Command;

use App\Domains\Billing\Models\CreditPurchase;
use App\Domains\Generation\Support\Money;
use App\Services\Payment\Data\CheckoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Credits a paid checkout. The return page and the webhook both land here, in either order and any number of times:
 * the row is locked and paid_at is written once, so a purchase is credited exactly once.
 */
final class CompleteCreditPurchase
{
    use AsAction;

    /** The purchase this checkout pays for, or null when it isn't one of ours. */
    public function handle(CheckoutSession $session): ?CreditPurchase
    {
        return DB::transaction(function () use ($session): ?CreditPurchase {
            $purchase = CreditPurchase::query()->lockForUpdate()->find($session->metadata['credit_purchase_id'] ?? null)
                ?? CreditPurchase::query()->lockForUpdate()->where('checkout_id', $session->id)->first();

            if ($purchase === null) {
                Log::warning('Paid checkout for an unknown credit purchase.', ['checkout' => $session->id]);

                return null;
            }

            if ($purchase->paid_at !== null || ! $session->paid) {
                return $purchase;
            }

            // Credit what was charged, never what was asked for: a mismatch means something's off, so a person looks.
            if ($session->currency !== 'usd' || Money::fromCents($session->amountCents) !== $purchase->amount_nd) {
                Log::error('Paid checkout does not match its credit purchase.', [
                    'purchase' => $purchase->id, 'checkout' => $session->id, 'amount_cents' => $session->amountCents, 'currency' => $session->currency,
                ]);

                return $purchase;
            }

            $purchase->forceFill(['checkout_id' => $session->id, 'paid_at' => now(), 'payment_ref' => $session->paymentRef])->save();

            return $purchase;
        });
    }
}
