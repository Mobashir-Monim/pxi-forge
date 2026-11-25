<?php

namespace App\Domains\Generation\Support;

use App\Services\ImageGeneration\Data\TokenRates;
use App\Services\ImageGeneration\Data\TokenUsage;
use InvalidArgumentException;

/**
 * Cost math in integer nanodollars ($0.000000001). Pixelforge never sends a price, so its cost is worked out from the
 * tokens it billed and the rates fixed on the generation, and the user pays that plus the markup.
 */
final class Money
{
    public const int MARKUP_PERCENT = 125;

    private const int NANO = 1_000_000_000;

    /** What Pixelforge charged us for these tokens at these rates. */
    public static function providerCost(TokenUsage $usage, TokenRates $rates): int
    {
        return self::fromPerMillion(
            $usage->inputText * self::perMillion($rates->inputText)
            + $usage->inputImage * self::perMillion($rates->inputImage)
            + $usage->output * self::perMillion($rates->output)
            + $usage->reasoning * self::perMillion($rates->reasoning),
        );
    }

    /**
     * What the user pays for a provider cost.
     * ponytail: rounds up to the next nanodollar, exact with today's rates. A rate with more decimals than they have
     * would make this round, always in our favour by under $0.000000001.
     */
    public static function charge(int $providerCost): int
    {
        return self::ceilDiv($providerCost * self::MARKUP_PERCENT, 100);
    }

    /**
     * Nanodollars per 1M tokens × tokens, down to nanodollars.
     * ponytail: exact for any rate with up to 3 decimals. Finer rates would round up here, by under $0.000000001.
     */
    public static function fromPerMillion(int $perMillionTotal): int
    {
        return self::ceilDiv($perMillionTotal, 1_000_000);
    }

    /**
     * A "USD per 1M tokens" decimal string as integer nanodollars per 1M tokens, so "12.50" is 12,500,000,000. Parsed
     * from the string, never through a float.
     */
    public static function perMillion(string $rate): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,9}))?$/', $rate, $m) !== 1) {
            throw new InvalidArgumentException("Unreadable token rate: {$rate}");
        }

        return (int) $m[1] * self::NANO + (int) str_pad($m[2] ?? '', 9, '0');
    }

    public static function ceilDiv(int $a, int $b): int
    {
        return intdiv($a + $b - 1, $b);
    }
}
