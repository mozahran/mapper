<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

/**
 * One sort key: what to read from an element, and which way round.
 */
final class Order
{
    public function __construct(
        private Path $path,
        private bool $descending = false,
    ) {
    }

    public function compare(mixed $left, mixed $right): int
    {
        $comparison = self::spaceship($this->read($left), $this->read($right));

        return $this->descending ? -$comparison : $comparison;
    }

    private function read(mixed $element): mixed
    {
        $value = $this->path->read($element);

        return $value === Missing::value() ? null : $value;
    }

    /**
     * Nulls sort before everything else ascending, so that the elements a payload has
     * no value for gather at one end instead of scattering through the result.
     */
    private static function spaceship(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return ($left === null ? 0 : 1) <=> ($right === null ? 0 : 1);
        }

        return $left <=> $right;
    }
}
