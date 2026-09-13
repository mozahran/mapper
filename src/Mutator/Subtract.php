<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

use Zahran\Mapper\V2\Number;

/**
 * Subtracts the argument from the piped value: value - argument.
 */
final class Subtract implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $minuend = Number::of($value);
        $subtrahend = Number::of($arguments[0] ?? 0);

        return $minuend === null || $subtrahend === null ? null : $minuend - $subtrahend;
    }
}
