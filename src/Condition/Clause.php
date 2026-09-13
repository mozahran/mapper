<?php

declare(strict_types=1);

namespace Zahran\Mapper\Condition;

use Zahran\Mapper\Missing;
use Zahran\Mapper\Path;

/**
 * One content test applied to an element: the value at a path inside it, weighed
 * against a hard-coded value by a registered condition.
 *
 * Clauses are what makes selection depend on the data rather than on position —
 * they drive both the {"where": …} path segment and the "where" of a list attribute.
 *
 * A path the element has no value for tests as null, so "notnull" and "null" say what
 * you would expect about a key that is not there at all.
 */
final class Clause
{
    public function __construct(
        private Predicate $predicate,
        private Path $path,
        private mixed $compare,
    ) {
    }

    public function matches(mixed $element): bool
    {
        $value = $this->path->read($element);

        return $this->predicate->matches($value === Missing::value() ? null : $value, $this->compare);
    }

    /**
     * Every clause must hold: clauses narrow, they never widen.
     *
     * @param list<self> $clauses
     */
    public static function allMatch(array $clauses, mixed $element): bool
    {
        foreach ($clauses as $clause) {
            if (!$clause->matches($element)) {
                return false;
            }
        }

        return true;
    }
}
