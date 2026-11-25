<?php

namespace App\Domains\Generation\Models;

use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Models\User;
use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to Pixelforge and what came of it. Money is integer nanodollars ($0.000000001).
 *
 * @property string $id
 * @property string $chat_id
 * @property string $user_id
 * @property string|null $retry_of_id
 * @property string|null $client_request_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $message
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<array{url: string, name: string, annotations: list<array<string, mixed>>}> $references
 * @property int $estimate_min_nd
 * @property int $estimate_max_nd
 * @property array<string, string>|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $provider_cost_nd
 * @property int|null $charge_nd
 * @property string|null $waiting_reason
 * @property int $submit_attempts
 * @property FailureReason|null $failure_reason
 * @property string|null $error_message
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $image_path
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $timed_out_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Chat $chat
 * @property-read User $user
 */
#[UseFactory(GenerationFactory::class)]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    /** Every column is written by our own actions, never straight from a request. */
    protected $guarded = [];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'references' => 'array',
            'rates' => 'array',
            'failure_reason' => FailureReason::class,
            'image_url_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'timed_out_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Chat, $this> */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::ACTIVE);
    }

    public function toRequest(): GenerationRequest
    {
        return new GenerationRequest(
            prompt: $this->prompt,
            size: $this->size,
            quality: $this->quality,
            referenceImages: array_column($this->references, 'url'),
            // Lets a webhook that beats the create response find this row before it knows Pixelforge's id.
            metadata: ['generation_id' => $this->id],
        );
    }

    /** Failed costs nothing, us or the user. */
    public function markFailed(FailureReason $reason, ?string $detail = null): void
    {
        $this->forceFill([
            'status' => GenerationStatus::Failed,
            'failure_reason' => $reason,
            'error_message' => $detail,
            'waiting_reason' => null,
            'provider_cost_nd' => 0,
            'charge_nd' => 0,
        ])->save();
    }
}
