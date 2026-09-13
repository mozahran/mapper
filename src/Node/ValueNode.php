<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Node;

use Zahran\Mapper\V2\Missing;
use Zahran\Mapper\V2\Pipeline;
use Zahran\Mapper\V2\Source;

final class ValueNode implements Node
{
    public function __construct(
        public string $name,
        private Source $source,
        private mixed $default = null,
        private ?Pipeline $pipeline = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function evaluate(mixed $scope): mixed
    {
        $value = $this->source->read($scope);

        if ($value === Missing::value()) {
            return $this->default;
        }

        if ($this->pipeline === null) {
            return $value;
        }

        return $this->pipeline->apply($value);
    }
}
