<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

/**
 * @internal
 */
final class Text
{
    private function __construct()
    {
    }

    public static function of(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
