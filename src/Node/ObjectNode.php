<?php

declare(strict_types=1);

namespace Zahran\Mapper\Node;

final class ObjectNode implements Node
{
    /**
     * @param list<Node> $children
     */
    public function __construct(
        public string $name,
        private array $children,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function name(): string
    {
        return $this->name;
    }

    public function evaluate(mixed $scope): array
    {
        $mapped = [];
        foreach ($this->children as $child) {
            $mapped[$child->name()] = $child->evaluate($scope);
        }

        return $mapped;
    }
}
