<?php

declare(strict_types=1);

namespace Zahran\Mapper;

/**
 * The sentinel a path returns when the payload has no value at all, as distinct from
 * a value that is present and null.
 */
final class Missing
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function value(): self
    {
        return self::$instance ??= new self();
    }
}
