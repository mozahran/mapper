<?php

declare(strict_types=1);

namespace Zahran\Mapper\Mutator;

use Zahran\Mapper\Step;

final class MutatorStep implements Step
{
    /**
     * @param list<mixed> $arguments
     */
    public function __construct(
        private Mutator $mutator,
        private array $arguments = [],
    ) {
    }

    public function apply(mixed $value): mixed
    {
        return $this->mutator->apply($value, $this->arguments);
    }
}
