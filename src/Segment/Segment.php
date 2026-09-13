<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Segment;

/**
 * One step of a path.
 *
 * A step reads a set of scopes and returns the set it resolves to, so that a single
 * grammar covers both the literal segments — which narrow one scope to one value —
 * and the content-based ones, which can widen a scope into many or drop it entirely.
 */
interface Segment
{
    /**
     * @param list<mixed> $scopes
     * @return list<mixed>
     */
    public function traverse(array $scopes): array;

    /**
     * Whether this step can resolve to more than one value, which makes the whole
     * path collection-shaped.
     */
    public function isMultiValued(): bool;
}
