<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Cast\Cast;
use Zahran\Mapper\V2\Cast\CastType;
use Zahran\Mapper\V2\Condition\ConditionType;
use Zahran\Mapper\V2\Condition\Predicate;
use Zahran\Mapper\V2\Mutator\Add;
use Zahran\Mapper\V2\Mutator\Divide;
use Zahran\Mapper\V2\Mutator\Modulo;
use Zahran\Mapper\V2\Mutator\Multiply;
use Zahran\Mapper\V2\Mutator\Mutator;
use Zahran\Mapper\V2\Mutator\NativeFunction;
use Zahran\Mapper\V2\Mutator\Power;
use Zahran\Mapper\V2\Mutator\Subtract;

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
