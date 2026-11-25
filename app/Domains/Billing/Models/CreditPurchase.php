<?php

namespace App\Domains\Billing\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credits a user is buying through a hosted checkout. They count toward the balance once paid. Money is integer
 * nanodollars ($0.000000001).
 *
 * @property string $id
 * @property string $user_id
 * @property string $client_request_id
 * @property string|null $checkout_id
 * @property int $amount_nd
 * @property CarbonImmutable|null $paid_at
 * @property string|null $payment_ref
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class CreditPurchase extends Model
{
    use HasUlids;

    /** Every column is written by our own actions, never straight from a request. */
    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopePaid(Builder $query): void
    {
        $query->whereNotNull('paid_at');
    }
}
