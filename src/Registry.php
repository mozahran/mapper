<?php

declare(strict_types=1);

namespace Zahran\Mapper;

use Zahran\Mapper\Cast\Cast;
use Zahran\Mapper\Cast\CastType;
use Zahran\Mapper\Condition\ConditionType;
use Zahran\Mapper\Condition\Predicate;
use Zahran\Mapper\Mutator\Add;
use Zahran\Mapper\Mutator\Divide;
use Zahran\Mapper\Mutator\Modulo;
use Zahran\Mapper\Mutator\Multiply;
use Zahran\Mapper\Mutator\Mutator;
use Zahran\Mapper\Mutator\NativeFunction;
use Zahran\Mapper\Mutator\Power;
use Zahran\Mapper\Mutator\Subtract;

final class Registry
{
    /**
     * @param array<string, Predicate> $conditions
     * @param array<string, Cast> $casts
     * @param array<string, Mutator> $mutators
     * @param array<string, int> $functions
     */
    private function __construct(
        private array $conditions,
        private array $casts,
        private array $mutators,
        private array $functions,
    ) {
    }

    public static function default(): self
    {
        $conditions = [];
        foreach (ConditionType::cases() as $condition) {
            $conditions[$condition->value] = $condition;
        }

        $casts = [];
        foreach (CastType::cases() as $cast) {
            $casts[$cast->value] = $cast;
        }

        return new self(
            $conditions,
            $casts,
            [
                'add' => new Add(),
                'divide' => new Divide(),
                'modulo' => new Modulo(),
                'multiply' => new Multiply(),
                'power' => new Power(),
                'subtract' => new Subtract(),
            ],
            array_flip(NativeFunction::ALLOWED),
        );
    }

    public function withCondition(string $type, Predicate $condition): self
    {
        return new self(array_merge($this->conditions, [$type => $condition]), $this->casts, $this->mutators, $this->functions);
    }

    public function withCast(string $type, Cast $cast): self
    {
        return new self($this->conditions, array_merge($this->casts, [$type => $cast]), $this->mutators, $this->functions);
    }

    public function withMutator(string $name, Mutator $mutator): self
    {
        return new self($this->conditions, $this->casts, array_merge($this->mutators, [$name => $mutator]), $this->functions);
    }

    public function withFunctions(string ...$functions): self
    {
        return new self(
            $this->conditions,
            $this->casts,
            $this->mutators,
            array_merge($this->functions, array_fill_keys(array_map('strtolower', $functions), 1)),
        );
    }

    public function condition(string $type): ?Predicate
    {
        return $this->conditions[$type] ?? null;
    }

    public function cast(string $type): ?Cast
    {
        return $this->casts[$type] ?? null;
    }

    public function mutator(string $name): ?Mutator
    {
        return $this->mutators[$name] ?? null;
    }

    public function allowsFunction(string $name): bool
    {
        return isset($this->functions[strtolower($name)]) && function_exists($name);
    }
}
