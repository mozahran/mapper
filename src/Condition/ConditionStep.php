<?php

declare(strict_types=1);

namespace Zahran\Mapper\Condition;

use Zahran\Mapper\Step;

final class ConditionStep implements Step
{
    public function __construct(
        private Predicate $predicate,
        private mixed $compare,
        private mixed $then,
        private mixed $otherwise,
    ) {
    }

    public function apply(mixed $value): mixed
    {
        if ($this->predicate->matches($value, $this->compare)) {
            return $this->then;
        }

        return $this->otherwise ?? $value;
    }
}
