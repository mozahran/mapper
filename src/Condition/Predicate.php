<?php

declare(strict_types=1);

namespace Zahran\Mapper\Condition;

interface Predicate
{
    public function matches(mixed $value, mixed $compare): bool;
}
