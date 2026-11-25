<?php

namespace App\Domains\Billing\Actions\Query;

use App\Domains\Billing\Models\CreditPurchase;
use App\Domains\Generation\Models\Generation;
use App\Domains\Generation\Support\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A user's credits, worked out from the records rather than stored: what they bought minus what finished generations
 * charged. A running generation holds its estimate's upper bound until it finishes, since its real price isn't known
 * yet. The real price never exceeds that bound, so a balance can't go below zero. Integer nanodollars.
 */
final class GetCreditBalance
{
    use AsAction;

    /**
     * @return array{balance: int, held: int, available: int}
     */
    public function handle(User $user): array
    {
        $bought = (int) CreditPurchase::query()->whereBelongsTo($user)->paid()->sum('amount_nd');
        $charged = (int) Generation::query()->whereBelongsTo($user)->sum('charge_nd');
        $held = (int) Generation::query()->whereBelongsTo($user)->active()->sum('estimate_max_nd');

        return ['balance' => $bought - $charged, 'held' => $held, 'available' => $bought - $charged - $held];
    }

    /**
     * Stops a generation the user can't pay for at its most expensive. Call inside the transaction that creates it: the
     * user row stays locked until commit, so two sends racing from different tabs can't both spend the same credits.
     */
    public static function ensureCanAfford(User $user, int $maxCharge): void
    {
        DB::table('users')->where('id', $user->id)->lockForUpdate()->value('id');

        $available = self::run($user)['available'];

        if ($available < $maxCharge) {
            throw ValidationException::withMessages(['credits' => sprintf(
                'Not enough credits. This image can cost up to %s and you have %s available. Buy credits to keep going.',
                Money::format($maxCharge, roundUp: true),
                Money::format(max($available, 0)),
            )]);
        }
    }
}
