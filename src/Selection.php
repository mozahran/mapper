<?php

declare(strict_types=1);

namespace Zahran\Mapper;

use Zahran\Mapper\Condition\Clause;

/**
 * Which elements of a source array a list attribute maps, in what order, and how many —
 * everything about the output that depends on the content rather than on the template.
 *
 * The steps run in the order they are declared here: elements that fail "where" are
 * dropped, what survives is put in "sort" order, "distinct" keeps the first of each
 * group, and "offset"/"limit" take a window of the result. Nothing is mapped until the
 * window is settled, so a limit is a limit on work as well as on output.
 */
final class Selection
{
    /**
     * @param list<Clause> $where
     * @param list<Order> $sort
     * @param list<Path>|null $distinct the paths whose values identify an element, or
     *                                  null when duplicates are kept
     */
    public function __construct(
        private array $where = [],
        private array $sort = [],
        private ?array $distinct = null,
        private int $offset = 0,
        private ?int $limit = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->where === []
            && $this->sort === []
            && $this->distinct === null
            && $this->offset === 0
            && $this->limit === null;
    }

    /**
     * @param list<mixed> $elements
     * @return list<mixed>
     */
    public function apply(array $elements): array
    {
        if ($this->where !== []) {
            $elements = array_values(array_filter(
                $elements,
                fn (mixed $element): bool => Clause::allMatch($this->where, $element),
            ));
        }

        if ($this->sort !== []) {
            usort($elements, fn (mixed $left, mixed $right): int => $this->compare($left, $right));
        }

        if ($this->distinct !== null) {
            $elements = $this->deduplicate($elements);
        }

        if ($this->offset !== 0 || $this->limit !== null) {
            $elements = array_values(array_slice($elements, $this->offset, $this->limit));
        }

        return $elements;
    }

    /**
     * Sort keys are weighed in turn, so the second only speaks where the first ties.
     * usort has been stable since PHP 8.0, so elements that tie on every key keep the
     * order the payload gave them.
     */
    private function compare(mixed $left, mixed $right): int
    {
        foreach ($this->sort as $order) {
            $comparison = $order->compare($left, $right);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    /**
     * @param list<mixed> $elements
     * @return list<mixed>
     */
    private function deduplicate(array $elements): array
    {
        $seen = [];
        $kept = [];

        foreach ($elements as $element) {
            $key = $this->identity($element);
            if (array_key_exists($key, $seen)) {
                continue;
            }

            $seen[$key] = true;
            $kept[] = $element;
        }

        return $kept;
    }

    /**
     * Identity is exact: serialising keeps 1 and "1" apart, which array keys would not.
     */
    private function identity(mixed $element): string
    {
        $values = [];
        foreach ($this->distinct as $path) {
            $value = $path->read($element);
            $values[] = $value === Missing::value() ? null : $value;
        }

        return serialize($values);
    }
}
