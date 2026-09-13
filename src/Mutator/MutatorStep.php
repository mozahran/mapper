<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Mutator;

use Zahran\Mapper\V2\Step;

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
