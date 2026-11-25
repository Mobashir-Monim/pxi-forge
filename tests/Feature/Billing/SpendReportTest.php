<?php

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Jobs\SubmitGeneration;
use App\Domains\Generation\Models\Chat;
use App\Domains\Generation\Models\Generation;
use App\Models\User;
use App\Services\ImageGeneration\Data\TokenUsage;
use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

/** Generates one image as the user and finishes it at the fake Pixelforge with these tokens. */
function finished(User $user, TokenUsage $usage): Generation
{
    test()->actingAs($user)->post(route('generations.store'), [
        'client_request_id' => (string) Str::ulid(), 'message' => 'A fox', 'prompt' => 'A fox', 'size' => '1024x1024', 'quality' => 'medium', 'references' => [],
    ])->assertSessionHasNoErrors();
    $generation = Generation::query()->whereBelongsTo($user)->latest('id')->firstOrFail();
    app()->call([new SubmitGeneration($generation->id), 'handle']);

    return ApplyProviderUpdate::run($generation->fresh(), FakeImageGenerator::complete($generation->fresh()->provider_id, $usage));
}

it('reports charges, provider cost and profit per completion day and user, matching Pixelforge', function () {
    $ann = withCredits(userWith(...Permission::MEMBER_DEFAULTS));
    $bob = withCredits(userWith(...Permission::MEMBER_DEFAULTS));

    $this->travelTo('2026-11-20 23:59:00');
    finished($ann, new TokenUsage(2, 0, 1100, 200)); // $0.0465 to Pixelforge
    $this->travelTo('2026-11-21 10:00:00');
    finished($ann, new TokenUsage(2, 0, 1100, 2000)); // $0.069
    finished($bob, new TokenUsage(2, 0, 1100, 200));

    $this->actingAs(userWith(Permission::ReportsView))
        ->get(route('reports.index', ['from' => '2026-11-20', 'to' => '2026-11-21']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/Index')
            ->where('pixelforge_error', null)
            ->where('days.0.date', '2026-11-21')
            ->where('days.0.generations', 2)
            ->where('days.0.cost', 115_520_000)
            ->where('days.0.charged', 144_400_000)
            ->where('days.0.profit', 28_880_000)
            ->where('days.0.pixelforge.cost', 115_520_000)
            ->where('days.0.users.0.id', $ann->id)
            ->where('days.1.date', '2026-11-20')
            ->where('days.1.cost', 46_510_000)
            ->where('days.1.pixelforge.cost', 46_510_000)
            ->where('users.0.id', $ann->id)
            ->where('users.0.generations', 2)
            ->where('users.0.cost', 115_520_000)
            ->where('totals.cost', 162_030_000)
            ->where('totals.pixelforge.cost', 162_030_000)
            ->where('totals.charged', 202_537_500));
});

it('counts a success that came after we gave up as our cost, free to the user', function () {
    $user = withCredits(userWith(...Permission::MEMBER_DEFAULTS));
    $this->actingAs($user)->post(route('generations.store'), [
        'client_request_id' => (string) Str::ulid(), 'message' => 'A fox', 'prompt' => 'A fox', 'size' => '1024x1024', 'quality' => 'medium', 'references' => [],
    ]);
    $generation = Generation::query()->sole();
    app()->call([new SubmitGeneration($generation->id), 'handle']);
    $generation->fresh()->markFailed(FailureReason::TimedOut);

    ApplyProviderUpdate::run($generation->fresh(), FakeImageGenerator::complete($generation->fresh()->provider_id, new TokenUsage(2, 0, 1100, 200)));

    $this->actingAs(userWith(Permission::ReportsView))->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totals.charged', 0)
            ->where('totals.cost', 46_510_000)
            ->where('totals.profit', -46_510_000)
            ->where('totals.pixelforge.cost', 46_510_000));
});

it('still shows our numbers when Pixelforge\'s usage can\'t be loaded', function () {
    $chat = Chat::factory()->create();
    Generation::factory()->for($chat)->create([
        'user_id' => $chat->user_id, 'status' => 'succeeded', 'provider_cost_nd' => 100, 'charge_nd' => 125, 'completed_at' => now(),
    ]);
    config(['services-arch.imageGeneration.default' => 'pixelforge', 'services.pixelforge.key' => 'pk_test']);
    Http::fake(['*/v1/usage*' => Http::response(['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']], 503)]);

    $this->actingAs(userWith(Permission::ReportsView))->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('totals.charged', 125)
            ->where('totals.pixelforge', null)
            ->where('days.0.pixelforge', null)
            ->where('pixelforge_error', fn (string $error) => str_starts_with($error, "Couldn't load Pixelforge's usage")));
});

it('is only for operators', function () {
    $this->actingAs(userWith(...Permission::MEMBER_DEFAULTS))->get(route('reports.index'))->assertForbidden();
});
