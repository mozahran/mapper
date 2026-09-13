<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

final class Path implements Source
{
    /**
     * @param list<string|int> $segments
     * @param non-empty-list<int|Literal>|null $selection
     */
    public function __construct(
        private array $segments,
        private ?array $selection = null,
    ) {
    }

    public function read(mixed $scope): mixed
    {
        foreach ($this->segments as $segment) {
            if (!is_array($scope) || !array_key_exists($segment, $scope)) {
                return $this->selection === null ? Missing::value() : $this->pick(null);
            }
            $scope = $scope[$segment];
        }

        return $this->selection === null ? $scope : $this->pick($scope);
    }

    /**
     * @return list<mixed>
     */
    private function pick(mixed $source): array
    {
        $picked = [];
        foreach ($this->selection as $pick) {
            if ($pick instanceof Literal) {
                $picked[] = $pick->value;
                continue;
            }
            $picked[] = is_array($source) && array_key_exists($pick, $source) ? $source[$pick] : null;
        }

        return $picked;
    }
}
