<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Segment;

/**
 * "@" — the scope itself, which is how an attribute inside a list of scalars names the
 * element it is standing on, there being no key to reach it by.
 */
final class Current implements Segment
{
    public const TOKEN = '@';

    public function traverse(array $scopes): array
    {
        return $scopes;
    }

    public function isMultiValued(): bool
    {
        return false;
    }
}
