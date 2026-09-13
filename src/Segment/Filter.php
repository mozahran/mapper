<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Segment;

use Zahran\Mapper\V2\Condition\Clause;

/**
 * {"where": …} — every value one level down that satisfies every clause, so that
 * ["items", {"where": …}, "sku"] reads the skus of the matching items alone.
 */
final class Filter implements Segment
{
    public const KEY = 'where';

    /**
     * @param non-empty-list<Clause> $clauses
     */
    public function __construct(
        private array $clauses,
    ) {
    }

    public function traverse(array $scopes): array
    {
        $found = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            foreach ($scope as $value) {
                if (Clause::allMatch($this->clauses, $value)) {
                    $found[] = $value;
                }
            }
        }

        return $found;
    }

    public function isMultiValued(): bool
    {
        return true;
    }
}
