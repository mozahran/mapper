<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Node;

use Zahran\Mapper\V2\Path;

final class ListNode implements Node
{
    public function __construct(
        public string $name,
        private Path $path,
        private ObjectNode $item,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function name(): string
    {
        return $this->name;
    }

    public function evaluate(mixed $scope): array
    {
        $source = $this->path->read($scope);

        if (!is_array($source)) {
            return [];
        }

        $mapped = [];
        foreach ($source as $element) {
            $mapped[] = $this->item->evaluate($element);
        }

        return $mapped;
    }
}
