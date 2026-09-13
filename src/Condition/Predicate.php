<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Condition;

interface Predicate
{
    public function matches(mixed $value, mixed $compare): bool;
}
