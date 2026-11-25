<?php

namespace App\Domains\Generation\Models;

use App\Domains\Generation\Enums\ChatStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ChatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A thread of generations. Only one runs at a time, and each finished one stays read-only in the thread.
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property ChatStatus $status
 * @property string|null $archived_reason
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'title', 'status'])]
#[UseFactory(ChatFactory::class)]
class Chat extends Model
{
    /** @use HasFactory<ChatFactory> */
    use HasFactory, HasUlids;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => ChatStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Generation, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function isArchived(): bool
    {
        return $this->status === ChatStatus::Archived;
    }

    public function archive(string $reason): void
    {
        $this->forceFill(['status' => ChatStatus::Archived, 'archived_reason' => $reason, 'archived_at' => now()])->save();
    }
}
