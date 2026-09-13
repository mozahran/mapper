<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

interface Mutator
{
    /**
     * @param list<mixed> $arguments
     */
    public function apply(mixed $value, array $arguments): mixed;
}
