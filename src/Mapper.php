<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Cast\Cast;
use Zahran\Mapper\V2\Condition\Predicate;
use Zahran\Mapper\V2\Mutator\Mutator;

final class Mapper
{
    /**
     * @param bool $strict see self::strict()
     */
    public function __construct(
        private Registry $registry,
        private bool $strict = false,
    ) {
    }

    public static function default(): self
    {
        return new self(Registry::default());
    }

    /**
     * Returns a mapper that refuses to invent values rather than coercing them: a cast
     * that cannot carry a value over faithfully fails instead of turning "abc" into 0,
     * and a list attribute whose source is not a list fails instead of yielding [].
     *
     * Nulls are carried through casts untouched, and a path the payload has no value for
     * still falls back on its default — absence is not an error unless the attribute
     * declares itself "required".
     */
    public function strict(bool $strict = true): self
    {
        return new self($this->registry, $strict);
    }

    /**
     * Returns the reusable mapping; compiling does not mutate the mapper.
     *
     * @param string|array<array-key, mixed> $template
     */
    public function compile(string|array $template): CompiledMapping
    {
        return new CompiledMapping(
            (new Compiler($this->registry, $this->strict))->compile(is_array($template) ? $template : Json::decode($template, 'mappings')),
        );
    }

    /**
     * Compiles and maps in one call. Prefer compile() when the same template is reused.
     *
     * @param string|array<array-key, mixed> $data
     * @param string|array<array-key, mixed> $template
     */
    public function map(string|array $data, string|array $template): mixed
    {
        return $this->compile($template)->map($data);
    }

    public function withCondition(string $type, Predicate $condition): self
    {
        return new self($this->registry->withCondition($type, $condition), $this->strict);
    }

    public function withCast(string $type, Cast $cast): self
    {
        return new self($this->registry->withCast($type, $cast), $this->strict);
    }

    public function withMutator(string $name, Mutator $mutator): self
    {
        return new self($this->registry->withMutator($name, $mutator), $this->strict);
    }

    public function withFunctions(string ...$functions): self
    {
        return new self($this->registry->withFunctions(...$functions), $this->strict);
    }
}
