<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Node\ObjectNode;

final class CompiledMapping
{
    public function __construct(
        private ObjectNode $root,
    ) {
    }

    /**
     * @param string|array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public function map(string|array $data): array
    {
        return $this->root->evaluate(is_array($data) ? $data : Json::decode($data, 'data'));
    }

    /**
     * Maps lazily: nothing is read until the returned generator is consumed.
     *
     * @param iterable<array-key, string|array<array-key, mixed>> $payloads
     * @return \Generator<array-key, array<string, mixed>>
     */
    public function mapMany(iterable $payloads): \Generator
    {
        foreach ($payloads as $key => $payload) {
            yield $key => $this->map($payload);
        }
    }
}
