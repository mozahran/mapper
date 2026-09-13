<?php

declare(strict_types=1);

namespace Zahran\Mapper;

use Zahran\Mapper\Node\Node;

final class CompiledMapping
{
    public function __construct(
        private Node $root,
    ) {
    }

    /**
     * Whatever the template's root describes: an object keyed by attribute name, a list
     * when the root is of type "array", or a bare value when the root is a single path.
     *
     * @param string|array<array-key, mixed> $data
     */
    public function map(string|array $data): mixed
    {
        return $this->root->evaluate(is_array($data) ? $data : Json::decode($data, 'data'));
    }

    /**
     * Maps lazily: nothing is read until the returned generator is consumed.
     *
     * @param iterable<array-key, string|array<array-key, mixed>> $payloads
     * @return \Generator<array-key, mixed>
     */
    public function mapMany(iterable $payloads): \Generator
    {
        foreach ($payloads as $key => $payload) {
            yield $key => $this->map($payload);
        }
    }
}
