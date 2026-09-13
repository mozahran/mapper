<?php

declare(strict_types=1);

namespace Zahran\Mapper\Segment;

/**
 * "*" — every value one level down, whether the scope is a list or a map.
 */
final class Wildcard implements Segment
{
    public const TOKEN = '*';

    public function traverse(array $scopes): array
    {
        $found = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            foreach ($scope as $value) {
                $found[] = $value;
            }
        }

        return $found;
    }

    public function isMultiValued(): bool
    {
        return true;
    }
}
