<?php

declare(strict_types=1);

namespace Zahran\Mapper\Node;

final class LiteralNode implements Node
{
    public function __construct(
        public string $name,
        private mixed $value,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function evaluate(mixed $scope): mixed
    {
        return $this->value;
    }
}
