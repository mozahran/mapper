<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Segment;

use Zahran\Mapper\V2\Arr;

/**
 * A literal key lookup: "customer", or 0.
 *
 * A negative integer counts from the end of a list — -1 is the last element — but only
 * after a literal lookup has failed, so a payload that really does hold the key -1 still
 * reads the way it always did.
 */
final class Key implements Segment
{
    public function __construct(
        private string|int $key,
    ) {
    }

    public function traverse(array $scopes): array
    {
        $found = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            if (array_key_exists($this->key, $scope)) {
                $found[] = $scope[$this->key];
                continue;
            }

            $index = $this->fromTheEnd($scope);
            if ($index !== null) {
                $found[] = $scope[$index];
            }
        }

        return $found;
    }

    public function isMultiValued(): bool
    {
        return false;
    }

    /**
     * @param array<array-key, mixed> $scope
     */
    private function fromTheEnd(array $scope): ?int
    {
        if (!is_int($this->key) || $this->key >= 0 || !Arr::isList($scope)) {
            return null;
        }

        $index = count($scope) + $this->key;

        return $index >= 0 ? $index : null;
    }
}
