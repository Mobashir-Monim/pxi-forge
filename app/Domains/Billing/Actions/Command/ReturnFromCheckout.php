<?php

namespace App\Domains\Billing\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Support\Money;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Shared\Exceptions\ProviderException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Where the checkout sends the user back. Reads the session from the provider and settles it right away, so credits
 * show up without waiting on the webhook. The webhook still settles it if the user never comes back.
 */
final class ReturnFromCheckout
{
    use AsAction;

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
        return ['session_id' => ['required', 'string', 'max:255']];
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $purchase = CompleteCreditPurchase::run($this->payments->findCheckout((string) $request->validated('session_id')));
        } catch (ProviderException) {
            $purchase = null;
        }

        // Someone else's checkout says nothing about this user's credits.
        if ($purchase === null || $purchase->user_id !== $user->id) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "We couldn't find that payment. If you were charged, your credits will show up shortly."]);
        } elseif ($purchase->paid_at === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Your payment is still processing. Credits are added as soon as it clears.']);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Added '.Money::format($purchase->amount_nd).' in credits.']);
        }

        return to_route('dashboard');
    }
}
