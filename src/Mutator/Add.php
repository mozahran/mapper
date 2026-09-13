<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

use Zahran\Mapper\V2\Number;

final class Add implements Mutator
{
    public function apply(mixed $value, array $arguments): mixed
    {
        $left = Number::of($value);
        $right = Number::of($arguments[0] ?? 0);

        return $left === null || $right === null ? null : $left + $right;
    }
}
