<?php

namespace App\Domains\Generation\Enums;

/** Why a generation failed, with what the user is told and whether a retry can help. */
enum FailureReason: string
{
    /** Pixelforge refused the prompt. The chat is archived. */
    case Blocked = 'content_policy_violation';

    /** Pixelforge accepted it, then couldn't produce an image for the prompt. */
    case GenerationFailed = 'generation_failed';

    /** Pixelforge had a problem on its side while generating. */
    case ProviderError = 'internal_error';

    /** Pixelforge stayed overloaded through every retry. */
    case Overloaded = 'overloaded';

    /** No free slot or request budget opened up in time. */
    case Busy = 'busy';

    /** The account's daily quota is used up until midnight UTC. */
    case DailyLimit = 'daily_limit_exceeded';

    /** No final state within the time limit. Pixelforge may still finish it. */
    case TimedOut = 'timed_out';

    /** Anything else Pixelforge rejected, or our own setup is broken. */
    case Rejected = 'rejected';

    public static function fromProviderCode(?string $code): self
    {
        return self::tryFrom((string) $code) ?? self::ProviderError;
    }

    public function retryable(): bool
    {
        return $this !== self::Blocked;
    }

    public function message(): string
    {
        return match ($this) {
            self::Blocked => 'This prompt was blocked by the content policy, so this chat is closed. You were not charged.',
            self::GenerationFailed => 'The image could not be generated for this prompt. You were not charged.',
            self::ProviderError => 'Something went wrong on the generator\'s side. You were not charged, and retrying usually works.',
            self::Overloaded => 'The generator is overloaded right now. You were not charged, try again in a moment.',
            self::Busy => 'The generator stayed busy for too long. You were not charged, try again in a moment.',
            self::DailyLimit => 'Today\'s generation limit is used up. It resets at midnight UTC. You were not charged.',
            self::TimedOut => 'This took too long, so we stopped waiting. You were not charged. If it still finishes, it will show up here for free.',
            self::Rejected => 'The generator rejected this request. You were not charged.',
        };
    }
}
