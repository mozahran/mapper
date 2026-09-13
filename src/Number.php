<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

/**
 * Coerces payload values to numbers for the arithmetic mutators.
 *
 * Evaluation never throws, so anything PHP would refuse to do arithmetic on — a
 * non-numeric string, null, an array — resolves to null and the mutator returns
 * null rather than raising a TypeError or a deprecation.
 *
 * @internal
 */
final class Number
{
    private function __construct()
    {
    }

    public static function of(mixed $value): int|float|null
    {
        return match (true) {
            is_int($value), is_float($value) => $value,
            is_bool($value) => (int) $value,
            is_string($value) && is_numeric($value) => $value + 0,
            default => null,
        };
    }

    public static function isZero(int|float $value): bool
    {
        return $value === 0 || $value === 0.0;
    }
}
