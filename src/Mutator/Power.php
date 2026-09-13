<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

use Zahran\Mapper\V2\Number;

/**
 * Raises the piped value to the power of the argument: value ** argument.
 *
 * A zero base with a negative exponent yields null; PHP deprecates that case.
 */
final class Power implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $base = Number::of($value);
        $exponent = Number::of($arguments[0] ?? 1);

        if ($base === null || $exponent === null) {
            return null;
        }

        if (Number::isZero($base) && $exponent < 0) {
            return null;
        }

        return $base ** $exponent;
    }
}
