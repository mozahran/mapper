<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Segment;

/**
 * "**" — the scope itself and every value nested anywhere beneath it, so that
 * ["**", "sku"] finds every "sku" in the payload no matter how deep it sits.
 */
final class Descendants implements Segment
{
    public const TOKEN = '**';

    public function traverse(array $scopes): array
    {
        $found = [];
        foreach ($scopes as $scope) {
            self::collect($scope, $found);
        }

        return $found;
    }

    public function isMultiValued(): bool
    {
        return true;
    }

    /**
     * @param list<mixed> $found
     */
    private static function collect(mixed $scope, array &$found): void
    {
        $found[] = $scope;

        if (!is_array($scope)) {
            return;
        }

        foreach ($scope as $value) {
            self::collect($value, $found);
        }
    }
}
