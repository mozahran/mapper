<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

use Zahran\Mapper\V2\Number;

/**
 * Divides the piped value by the argument: value / argument.
 *
 * A zero divisor yields null instead of the DivisionByZeroError PHP would raise.
 */
final class Divide implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $dividend = Number::of($value);
        $divisor = Number::of($arguments[0] ?? 1);

        if ($dividend === null || $divisor === null || Number::isZero($divisor)) {
            return null;
        }

        return $dividend / $divisor;
    }
}
