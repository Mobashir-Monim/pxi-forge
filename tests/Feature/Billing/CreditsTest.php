<?php

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Billing\Actions\Query\GetCreditBalance;
use App\Domains\Billing\Models\CreditPurchase;
use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Jobs\SubmitGeneration;
use App\Domains\Generation\Models\Generation;
use App\Domains\Generation\Support\Money;
use App\Models\User;
use App\Services\ImageGeneration\Data\TokenUsage;
use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Events\CheckoutPaid;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

function member(int $cents = 0): User
{
    $user = userWith(...Permission::MEMBER_DEFAULTS);

    return $cents > 0 ? withCredits($user, $cents) : $user;
}

/** @return array<string, mixed> */
function prompt(string $text = 'A lighthouse at dawn'): array
{
    return ['client_request_id' => (string) Str::ulid(), 'message' => $text, 'prompt' => $text, 'size' => '1024x1024', 'quality' => 'medium', 'references' => []];
}

function sendAs(User $user): Generation
{
    test()->actingAs($user)->post(route('generations.store'), prompt())->assertSessionHasNoErrors();
    $generation = Generation::query()->whereBelongsTo($user)->latest('id')->firstOrFail();
    app()->call([new SubmitGeneration($generation->id), 'handle']);

    return $generation->fresh();
}

/** Buys a pack through the fake checkout and comes back from it, returning the checkout id. */
function buy(User $user, int $cents, ?string $clientRequestId = null): string
{
    test()->actingAs($user)->post(route('credits.checkout'), ['client_request_id' => $clientRequestId ?? (string) Str::ulid(), 'amount_cents' => $cents]);

    return CreditPurchase::query()->whereBelongsTo($user)->latest('id')->value('checkout_id');
}

it('blocks generating without enough credits, and saves nothing', function () {
    $user = member();

    $this->actingAs($user)->post(route('generations.store'), prompt())->assertSessionHasErrors('credits');

    expect(Generation::query()->count())->toBe(0);
});

it('holds the estimate\'s upper bound while running, then charges the real price and releases the rest', function () {
    $user = member(100);
    $generation = sendAs($user);

    expect(GetCreditBalance::run($user))->toBe([
        'balance' => Money::fromCents(100),
        'held' => $generation->estimate_max_nd,
        'available' => Money::fromCents(100) - $generation->estimate_max_nd,
    ]);

    ApplyProviderUpdate::run($generation, FakeImageGenerator::complete($generation->provider_id, new TokenUsage(5, 0, 1100, 1500)));
    $charge = $generation->fresh()->charge_nd;

    expect($charge)->toBeGreaterThan(0)->toBeLessThanOrEqual($generation->estimate_max_nd)
        ->and(GetCreditBalance::run($user))->toBe(['balance' => Money::fromCents(100) - $charge, 'held' => 0, 'available' => Money::fromCents(100) - $charge]);
});

it('gives the whole hold back when a generation fails', function () {
    $user = member(100);
    $generation = sendAs($user);

    ApplyProviderUpdate::run($generation, FakeImageGenerator::fail($generation->provider_id, 'internal_error'));

    expect(GetCreditBalance::run($user))->toBe(['balance' => Money::fromCents(100), 'held' => 0, 'available' => Money::fromCents(100)]);
});

it('counts running generations in other chats, so credits can\'t be spent twice', function () {
    $user = member();
    // Just enough for one image at its most expensive.
    $estimate = $this->actingAs($user)->getJson(route('generations.estimate', ['prompt' => 'A lighthouse at dawn', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => 0]))->json('max');
    CreditPurchase::query()->create(['user_id' => $user->id, 'client_request_id' => (string) Str::ulid(), 'amount_nd' => $estimate, 'paid_at' => now()]);

    sendAs($user);

    $this->actingAs($user)->post(route('generations.store'), prompt())->assertSessionHasErrors('credits');
    expect(Generation::query()->count())->toBe(1);
});

it('blocks a retry the user can no longer afford', function () {
    $user = member(100);
    $generation = sendAs($user);
    ApplyProviderUpdate::run($generation, FakeImageGenerator::fail($generation->provider_id, 'internal_error'));
    CreditPurchase::query()->whereBelongsTo($user)->update(['paid_at' => null]);

    $this->actingAs($user)->post(route('generations.retry', $generation))->assertSessionHasErrors('credits');
});

it('credits a pack once the checkout is paid, however often it is confirmed', function () {
    $user = member();
    $checkoutId = buy($user, 1000);

    $purchase = CreditPurchase::query()->sole();
    expect($purchase->paid_at)->toBeNull()
        ->and(GetCreditBalance::run($user)['balance'])->toBe(0);

    // The return page, then the webhook twice: one credit.
    $this->actingAs($user)->get(route('credits.return', ['session_id' => $checkoutId]))->assertRedirect(route('dashboard'));
    $session = new CheckoutSession($checkoutId, null, true, 1000, 'usd', 'pi_1', ['credit_purchase_id' => $purchase->id]);
    CheckoutPaid::dispatch('evt_1', $session);
    CheckoutPaid::dispatch('evt_1', $session);

    expect(GetCreditBalance::run($user)['balance'])->toBe(Money::fromCents(1000))
        ->and($purchase->fresh()->paid_at)->not->toBeNull();
});

it('makes one purchase for a double-clicked buy', function () {
    $user = member();
    $click = (string) Str::ulid();

    buy($user, 500, $click);
    buy($user, 500, $click);

    expect(CreditPurchase::query()->count())->toBe(1);
});

it('only sells the listed packs', function () {
    $this->actingAs(member())->post(route('credits.checkout'), ['client_request_id' => (string) Str::ulid(), 'amount_cents' => 1])
        ->assertSessionHasErrors('amount_cents');

    expect(CreditPurchase::query()->count())->toBe(0);
});

it('refuses to credit a checkout whose amount doesn\'t match the purchase', function () {
    $user = member();
    $checkoutId = buy($user, 500);
    $purchase = CreditPurchase::query()->sole();

    CheckoutPaid::dispatch('evt_1', new CheckoutSession($checkoutId, null, true, 50, 'usd', 'pi_1', ['credit_purchase_id' => $purchase->id]));

    expect($purchase->fresh()->paid_at)->toBeNull();
});

it('gates buying and seeing credits on their own permissions', function () {
    $user = userWith(Permission::GenerationsCreate);

    $this->actingAs($user)->post(route('credits.checkout'), ['client_request_id' => (string) Str::ulid(), 'amount_cents' => 500])->assertForbidden();
    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('credits', null));
    $this->actingAs(member())->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('credits.balance', 0));
});
