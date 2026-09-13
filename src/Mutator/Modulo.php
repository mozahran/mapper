<?php

declare(strict_types=1);

namespace Zahran\Mapper\Mutator;

use Zahran\Mapper\Number;

/**
 * The remainder of dividing the piped value by the argument: value % argument.
 *
 * Floats use fmod(), because PHP's % truncates them to int and deprecates the
 * precision it loses. A zero — or absent — divisor yields null.
 */
final class Modulo implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $dividend = Number::of($value);
        $divisor = Number::of($arguments[0] ?? 0);

        if ($dividend === null || $divisor === null || Number::isZero($divisor)) {
            return null;
        }

        if (is_float($dividend) || is_float($divisor)) {
            return fmod($dividend, $divisor);
        }

        return $dividend % $divisor;
    }
}
