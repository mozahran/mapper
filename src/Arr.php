<?php

declare(strict_types=1);

namespace Zahran\Mapper;

/**
 * The array helpers PHP only grew after 8.0.
 *
 * @internal
 */
final class Arr
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function last(array $value): mixed
    {
        return $value === [] ? null : $value[array_key_last($value)];
    }
}
