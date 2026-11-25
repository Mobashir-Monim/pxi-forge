<?php

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Domains\Generation\Jobs\StoreGeneratedImage;
use App\Domains\Generation\Jobs\SubmitGeneration;
use App\Domains\Generation\Jobs\WatchGeneration;
use App\Domains\Generation\Models\Chat;
use App\Domains\Generation\Models\Generation;
use App\Models\User;
use App\Services\ImageGeneration\Data\TokenUsage;
use App\Services\ImageGeneration\Events\GenerationUpdated;
use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use App\Services\ObjectStore\ObjectStoreService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

function generator(): User
{
    return userWith(...Permission::MEMBER_DEFAULTS);
}

/** @return array<string, mixed> */
function sendPayload(array $overrides = []): array
{
    return [
        'client_request_id' => (string) Str::ulid(),
        'message' => 'A lighthouse at dawn',
        'prompt' => 'A lighthouse at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
        'references' => [],
        ...$overrides,
    ];
}

/** Sends a prompt as the user and runs the submit job, so the generation is accepted by the fake Pixelforge. */
function acceptedGeneration(?User $user = null, string $prompt = 'A lighthouse at dawn'): Generation
{
    test()->actingAs($user ?? generator())->post(route('generations.store'), sendPayload(['message' => $prompt, 'prompt' => $prompt]));
    $generation = Generation::query()->latest('id')->firstOrFail();
    app()->call([new SubmitGeneration($generation->id), 'handle']);

    return $generation->fresh();
}

/** The usage in the docs' succeeded example: $0.30551 at Pixelforge, so $0.3818875 to the user. */
function docsUsage(): TokenUsage
{
    return new TokenUsage(inputText: 12, inputImage: 765, output: 6450, reasoning: 3184);
}

it('saves a pending generation in a new chat before Pixelforge hears of it', function () {
    $user = generator();

    $this->actingAs($user)->post(route('generations.store'), sendPayload())->assertRedirect();

    $generation = Generation::query()->sole();

    expect($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->chat->user_id)->toBe($user->id)
        ->and($generation->estimate_min_nd)->toBeGreaterThan(0)
        ->and($generation->estimate_max_nd)->toBeGreaterThan($generation->estimate_min_nd);

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generationId === $generation->id);
});

it('creates one generation for a double-submitted click', function () {
    $user = generator();
    $payload = sendPayload();

    $this->actingAs($user)->post(route('generations.store'), $payload);
    $this->actingAs($user)->post(route('generations.store'), $payload);

    expect(Generation::query()->count())->toBe(1)->and(Chat::query()->count())->toBe(1);
    Queue::assertPushed(SubmitGeneration::class, 1);
});

it('runs one generation per chat at a time, enforced by the database too', function () {
    $user = generator();
    $this->actingAs($user)->post(route('generations.store'), sendPayload());
    $chat = Chat::query()->sole();

    $this->actingAs($user)->post(route('chats.generations.store', $chat), sendPayload())->assertSessionHasErrors('message');

    expect(fn () => Generation::factory()->for($chat)->create())->toThrow(UniqueConstraintViolationException::class);
});

it('hides other users\' chats and refuses users without the permission', function () {
    $chat = Chat::factory()->create();

    $this->actingAs(generator())->get(route('chats.show', $chat))->assertNotFound();
    $this->actingAs(generator())->post(route('chats.generations.store', $chat), sendPayload())->assertNotFound();
    $this->actingAs(userWith())->post(route('generations.store'), sendPayload())->assertForbidden();
});

it('records Pixelforge\'s id and rates on acceptance and starts watching', function () {
    $generation = acceptedGeneration();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->provider_id)->toStartWith('gen_')
        ->and($generation->rates)->toEqual(['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'])
        ->and($generation->accepted_at)->not->toBeNull();

    Queue::assertPushed(WatchGeneration::class);
});

it('fails a blocked prompt for free and archives its chat for good', function () {
    $user = generator();
    $generation = acceptedGeneration($user, 'something [blocked]');

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->failure_reason)->toBe(FailureReason::Blocked)
        ->and($generation->charge_nd)->toBe(0)
        ->and($generation->chat->isArchived())->toBeTrue();

    $this->actingAs($user)->post(route('chats.generations.store', $generation->chat), sendPayload())->assertSessionHasErrors('message');
    $this->actingAs($user)->post(route('generations.retry', $generation))->assertSessionHasErrors('generation');
});

it('retries an overload a few times, then fails it with a retry button', function () {
    $user = generator();
    $this->actingAs($user)->post(route('generations.store'), sendPayload(['prompt' => 'a fox [overloaded]']));
    $generation = Generation::query()->sole();

    foreach (range(1, SubmitGeneration::MAX_ATTEMPTS - 1) as $attempt) {
        app()->call([new SubmitGeneration($generation->id), 'handle']);
        expect($generation->fresh()->waiting_reason)->toBe('overloaded');
    }

    app()->call([new SubmitGeneration($generation->id), 'handle']);
    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->failure_reason)->toBe(FailureReason::Overloaded)
        ->and($generation->failure_reason->retryable())->toBeTrue();
});

it('charges exactly the token cost plus 25% on success, once, however often the webhook arrives', function () {
    $generation = acceptedGeneration();
    $done = FakeImageGenerator::complete($generation->provider_id, docsUsage());

    GenerationUpdated::dispatch('evt_1', $done);
    GenerationUpdated::dispatch('evt_1', $done);
    // The same news under another event id, or from a poll, must not charge again either.
    GenerationUpdated::dispatch('evt_2', $done);
    ApplyProviderUpdate::run($generation, $done);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost_nd)->toBe(305_510_000)
        ->and($generation->charge_nd)->toBe(381_887_500)
        ->and($generation->output_tokens)->toBe(6450)
        ->and($generation->completed_at)->not->toBeNull();

    $this->assertDatabaseCount('webhook_events', 2);
    Queue::assertPushed(StoreGeneratedImage::class, 1);
});

it('never moves a finished generation back when an old event arrives late', function () {
    $generation = acceptedGeneration();
    $remote = FakeImageGenerator::complete($generation->provider_id, docsUsage());
    GenerationUpdated::dispatch('evt_done', $remote);

    $stale = FakeImageGenerator::snapshot($generation->provider_id, ['status' => 'processing', 'usage' => null]);
    GenerationUpdated::dispatch('evt_processing', $stale);

    expect($generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('charges nothing for a failed generation', function () {
    $generation = acceptedGeneration();

    GenerationUpdated::dispatch('evt_1', FakeImageGenerator::fail($generation->provider_id, 'internal_error'));
    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->failure_reason)->toBe(FailureReason::ProviderError)
        ->and($generation->provider_cost_nd)->toBe(0)
        ->and($generation->charge_nd)->toBe(0);
});

it('matches a webhook that beats the create response by the generation id in metadata', function () {
    $generation = acceptedGeneration();
    $generation->forceFill(['provider_id' => null, 'status' => GenerationStatus::Pending])->save();

    GenerationUpdated::dispatch('evt_1', FakeImageGenerator::complete(FakeImageGenerator::idFor($generation->id), docsUsage()));

    expect($generation->fresh())->status->toBe(GenerationStatus::Succeeded)->provider_id->toStartWith('gen_');
});

it('polls at 90s, then every 30s from 120s, up to 300s', function () {
    $generation = Generation::factory()->make(['accepted_at' => now()]);
    $next = fn (): int => (int) $generation->accepted_at->diffInSeconds(WatchGeneration::nextCheck($generation));

    expect($next())->toBe(90);

    $this->travel(90)->seconds();
    expect($next())->toBe(120);

    $this->travel(30)->seconds();
    expect($next())->toBe(150);

    $this->travel(160)->seconds();
    expect($next())->toBe(300);
});

it('fails a generation for free at the time limit, then shows it free if it finishes after all', function () {
    $generation = acceptedGeneration();

    $this->travel(WatchGeneration::TIME_LIMIT + 1)->seconds();
    app()->call([new WatchGeneration($generation->id), 'handle']);
    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->failure_reason)->toBe(FailureReason::TimedOut)
        ->and($generation->charge_nd)->toBe(0);

    GenerationUpdated::dispatch('evt_late', FakeImageGenerator::complete($generation->provider_id, docsUsage()));
    $generation->refresh();

    // Shown, never charged, and our cost still recorded so the admin report matches Pixelforge's usage.
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nd)->toBe(0)
        ->and($generation->provider_cost_nd)->toBe(305_510_000);
});

it('picks up the result by polling when the webhook never comes', function () {
    $generation = acceptedGeneration();
    FakeImageGenerator::complete($generation->provider_id, docsUsage());

    $this->travel(90)->seconds();
    app()->call([new WatchGeneration($generation->id), 'handle']);

    expect($generation->fresh())->status->toBe(GenerationStatus::Succeeded)->charge_nd->toBe(381_887_500);
});

it('retries a failed generation as a new one with its own idempotency key, once', function () {
    $user = generator();
    $failed = acceptedGeneration($user);
    GenerationUpdated::dispatch('evt_1', FakeImageGenerator::fail($failed->provider_id, 'internal_error'));

    $this->actingAs($user)->post(route('generations.retry', $failed))->assertRedirect(route('chats.show', $failed->chat_id));
    $this->actingAs($user)->post(route('generations.retry', $failed))->assertSessionHasErrors('generation');

    $retry = Generation::query()->where('retry_of_id', $failed->id)->sole();

    expect($retry->id)->not->toBe($failed->id)
        ->and($retry->status)->toBe(GenerationStatus::Pending)
        ->and($retry->prompt)->toBe($failed->prompt);

    $this->actingAs(userWith(Permission::ChatsView))->post(route('generations.retry', $failed))->assertForbidden();
});

it('keeps a copy of the image, since Pixelforge\'s link expires after a day', function () {
    Http::fake(['*' => Http::response('png-bytes')]);
    $generation = acceptedGeneration();
    ApplyProviderUpdate::run($generation, FakeImageGenerator::complete($generation->provider_id, docsUsage()));

    app()->call([new StoreGeneratedImage($generation->id), 'handle']);

    expect($generation->fresh()->image_path)->toBe("generations/{$generation->id}.png")
        ->and(app(ObjectStoreService::class)->get("generations/{$generation->id}.png"))->toBe('png-bytes');
});

it('shows the thread with what the user is charged, never our cost', function () {
    $user = generator();
    $generation = acceptedGeneration($user);
    GenerationUpdated::dispatch('evt_1', FakeImageGenerator::complete($generation->provider_id, docsUsage()));

    $this->actingAs($user)->get(route('chats.show', $generation->chat_id))
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('generations.0.charge', 381_887_500)
            ->missing('generations.0.provider_cost_nd')
            ->where('chats.0.id', $generation->chat_id));
});
