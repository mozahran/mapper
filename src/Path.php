<?php

declare(strict_types=1);

namespace Zahran\Mapper;

use Zahran\Mapper\Segment\Segment;

/**
 * Walks the payload one segment at a time.
 *
 * A path made only of literal keys resolves to a single value. Once a segment can match
 * more than one value — a wildcard, a recursive descent, a {"where": …} filter — the
 * path is collection-shaped and resolves to the list of every match.
 *
 * Either way a path that matched nothing at all reports Missing::value(), so a
 * collection that came back empty falls back on the attribute's default the same way a
 * key that was not there does. The compiler gives collection-shaped attributes an empty
 * list as their default, so "no matches" reads as [] unless the template says otherwise.
 */
final class Path implements Source
{
    private bool $multiValued;

    /**
     * @param list<Segment> $segments
     * @param non-empty-list<int|Literal>|null $picks fixed positions to read out of the
     *                                                resolved array, with hard-coded
     *                                                values allowed between them
     */
    public function __construct(
        private array $segments,
        private ?array $picks = null,
    ) {
        $this->multiValued = false;
        foreach ($segments as $segment) {
            if ($segment->isMultiValued()) {
                $this->multiValued = true;
                break;
            }
        }
    }

    /**
     * The path to the scope itself, for a list attribute that maps whatever it is
     * already standing on and for a clause that tests an element rather than a key
     * inside it.
     */
    public static function identity(): self
    {
        return new self([]);
    }

    public function read(mixed $scope): mixed
    {
        $scopes = [$scope];
        foreach ($this->segments as $segment) {
            $scopes = $segment->traverse($scopes);
            if ($scopes === []) {
                break;
            }
        }

        if ($this->picks !== null) {
            return $this->pick($this->multiValued ? $scopes : ($scopes[0] ?? null));
        }

        if ($scopes === []) {
            return Missing::value();
        }

        return $this->multiValued ? $scopes : $scopes[0];
    }

    /**
     * Whether this path resolves to a list of every match rather than to a single value.
     */
    public function isMultiValued(): bool
    {
        return $this->multiValued;
    }

    /**
     * @return list<mixed>
     */
    private function pick(mixed $source): array
    {
        $picked = [];
        foreach ($this->picks as $pick) {
            if ($pick instanceof Literal) {
                $picked[] = $pick->value;
                continue;
            }
            $picked[] = is_array($source) && array_key_exists($pick, $source) ? $source[$pick] : null;
        }

        return $picked;
    }
}
