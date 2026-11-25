<?php

namespace App\Domains\Billing\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Billing\Models\CreditPurchase;
use App\Domains\Generation\Support\Money;
use App\Models\User;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\PaymentService;
use App\Services\Shared\Exceptions\ProviderException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts buying a credit pack: records the purchase as unpaid, then sends the user to a hosted checkout. Credits only
 * count once the payment is confirmed (see CompleteCreditPurchase).
 */
final class BuyCredits
{
    use AsAction;

    /** The packs on offer, in US cents. Fixed amounts, so a request can't pick its own price. */
    public const array PACKS_CENTS = [500, 1000, 2500];

    public function __construct(private readonly PaymentService $payments) {}

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::CreditsPurchase->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'ulid'],
            'amount_cents' => ['required', 'integer', Rule::in(self::PACKS_CENTS)],
        ];
    }

    /** The checkout to send the user to. The same click again gets the same purchase and the same checkout. */
    public function handle(User $user, string $clientRequestId, int $amountCents): string
    {
        try {
            $purchase = CreditPurchase::query()->firstOrCreate(
                ['client_request_id' => $clientRequestId, 'user_id' => $user->id],
                ['amount_nd' => Money::fromCents($amountCents)],
            );
        } catch (UniqueConstraintViolationException) {
            // Lost a race with the same click, or someone else's id: either way only the owner's own row is reused.
            $purchase = CreditPurchase::query()->where('client_request_id', $clientRequestId)->where('user_id', $user->id)->firstOrFail();
        }

        // Paid already: never open a second checkout someone could pay again for the same credits.
        if ($purchase->paid_at !== null) {
            return route('dashboard');
        }

        $amount = Money::format($purchase->amount_nd);

        // The purchase id is the idempotency key, so a resend gets back the checkout already opened, never a second one.
        $checkout = $this->payments->createCheckout(new CheckoutRequest(
            amountCents: intdiv($purchase->amount_nd, Money::fromCents(1)),
            description: "{$amount} in Pxi Forge credits",
            successUrl: route('credits.return').'?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: route('dashboard'),
            idempotencyKey: $purchase->id,
            customerEmail: $user->email,
            metadata: ['credit_purchase_id' => $purchase->id],
        ));

        $purchase->checkout_id ??= $checkout->id;
        $purchase->save();

        return $checkout->url ?? route('credits.return', ['session_id' => $checkout->id]);
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $url = $this->handle($user, (string) $request->validated('client_request_id'), (int) $request->validated('amount_cents'));
        } catch (ProviderException $e) {
            report($e);
            Inertia::flash('toast', ['type' => 'error', 'message' => "Checkout isn't available right now. Nothing was charged, try again in a moment."]);

            return back();
        }

        // A full-page visit to the provider's domain, which an Inertia redirect can't do.
        return Inertia::location($url);
    }
}
