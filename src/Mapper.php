<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Cast\Cast;
use Zahran\Mapper\V2\Condition\Predicate;
use Zahran\Mapper\V2\Mutator\Mutator;

final class Mapper
{
    public function __construct(
        private Registry $registry,
    ) {
    }

    public static function default(): self
    {
        return new self(Registry::default());
    }

    /**
     * Returns the reusable mapping; compiling does not mutate the mapper.
     *
     * @param string|array<array-key, mixed> $template
     */
    public function compile(string|array $template): CompiledMapping
    {
        return new CompiledMapping(
            (new Compiler($this->registry))->compile(is_array($template) ? $template : Json::decode($template, 'mappings')),
        );
    }

    /**
     * Compiles and maps in one call. Prefer compile() when the same template is reused.
     *
     * @param string|array<array-key, mixed> $data
     * @param string|array<array-key, mixed> $template
     * @return array<string, mixed>
     */
    public function map(string|array $data, string|array $template): array
    {
        return $this->compile($template)->map($data);
    }

    public function withCondition(string $type, Predicate $condition): self
    {
        return new self($this->registry->withCondition($type, $condition));
    }

    public function withCast(string $type, Cast $cast): self
    {
        return new self($this->registry->withCast($type, $cast));
    }

    public function withMutator(string $name, Mutator $mutator): self
    {
        return new self($this->registry->withMutator($name, $mutator));
    }

    public function withFunctions(string ...$functions): self
    {
        return new self($this->registry->withFunctions(...$functions));
    }
}
