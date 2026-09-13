<?php

declare(strict_types=1);

namespace Zahran\Mapper\Mutator;

use Zahran\Mapper\Number;

final class Add implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $left = Number::of($value);
        $right = Number::of($arguments[0] ?? 0);

        return $left === null || $right === null ? null : $left + $right;
    }
}
