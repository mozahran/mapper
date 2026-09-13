<?php

declare(strict_types=1);

namespace Zahran\Mapper;

/**
 * Reads several sources into one list, so that a mutator can combine them into a
 * single value — "first_name" and "last_name" imploded into a full name, a net and
 * a tax path summed into a total.
 *
 * A path that the payload has no value for contributes null, which keeps the gathered
 * list aligned with the declared order. Only when every path is missing does the whole
 * source count as missing, so the attribute's "default" stands in for the lot.
 */
final class Gather implements Source
{
    /**
     * @var non-empty-list<Path|Literal>
     */
    private array $sources;

    /**
     * @param non-empty-list<Path|Literal> $sources
     */
    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function read(mixed $scope): mixed
    {
        $values = [];
        $paths = 0;
        $missing = 0;

        foreach ($this->sources as $source) {
            if ($source instanceof Literal) {
                $values[] = $source->value;
                continue;
            }

            ++$paths;
            $value = $source->read($scope);

            if ($value === Missing::value()) {
                ++$missing;
                $value = null;
            }

            $values[] = $value;
        }

        return $paths > 0 && $missing === $paths ? Missing::value() : $values;
    }
}
