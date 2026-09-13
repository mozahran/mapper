<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;
use Zahran\Mapper\V2\Mutator\Add;
use Zahran\Mapper\V2\Mutator\Divide;
use Zahran\Mapper\V2\Mutator\Modulo;
use Zahran\Mapper\V2\Mutator\Multiply;
use Zahran\Mapper\V2\Mutator\Mutator;
use Zahran\Mapper\V2\Mutator\Power;
use Zahran\Mapper\V2\Mutator\Subtract;

final class ArithmeticMutatorTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    /**
     * @param list<mixed> $arguments
     * @dataProvider provideArithmetic
     */
    public function testApplies(Mutator $mutator, mixed $value, array $arguments, mixed $expected): void
    {
        $this->assertSame($expected, $mutator->apply($value, $arguments));
    }

    /**
     * @return iterable<string, array{Mutator, mixed, list<mixed>, mixed}>
     */
    public static function provideArithmetic(): iterable
    {
        yield 'add integers' => [new Add(), 10, [5], 15];
        yield 'add a float' => [new Add(), 10, [0.5], 10.5];
        yield 'add a negative' => [new Add(), 10, [-4], 6];
        yield 'add a numeric string' => [new Add(), '10', ['5'], 15];
        yield 'add without an argument' => [new Add(), 10, [], 10];

        yield 'subtract integers' => [new Subtract(), 10, [4], 6];
        yield 'subtract past zero' => [new Subtract(), 4, [10], -6];
        yield 'subtract a float' => [new Subtract(), 10, [0.5], 9.5];
        yield 'subtract without an argument' => [new Subtract(), 10, [], 10];

        yield 'multiply integers' => [new Multiply(), 10, [5], 50];
        yield 'multiply by a float' => [new Multiply(), 10, [0.5], 5.0];
        yield 'multiply without an argument' => [new Multiply(), 10, [], 10];

        yield 'divide evenly' => [new Divide(), 10, [2], 5];
        yield 'divide with a remainder' => [new Divide(), 10, [4], 2.5];
        yield 'divide by a float' => [new Divide(), 10, [0.5], 20.0];
        yield 'divide without an argument' => [new Divide(), 10, [], 10];

        yield 'modulo integers' => [new Modulo(), 10, [3], 1];
        yield 'modulo a negative dividend' => [new Modulo(), -10, [3], -1];
        yield 'modulo floats uses fmod' => [new Modulo(), 7.5, [2], 1.5];

        yield 'power integers' => [new Power(), 2, [10], 1024];
        yield 'power of a fraction' => [new Power(), 9, [0.5], 3.0];
        yield 'power of a negative exponent' => [new Power(), 2, [-1], 0.5];
        yield 'power without an argument' => [new Power(), 7, [], 7];
    }

    /**
     * @param list<mixed> $arguments
     * @dataProvider provideUncomputable
     */
    public function testUncomputableArithmeticYieldsNullInsteadOfThrowing(Mutator $mutator, mixed $value, array $arguments): void
    {
        $this->assertNull($mutator->apply($value, $arguments));
    }

    /**
     * @return iterable<string, array{Mutator, mixed, list<mixed>}>
     */
    public static function provideUncomputable(): iterable
    {
        yield 'divide by zero' => [new Divide(), 10, [0]];
        yield 'divide by a zero float' => [new Divide(), 10, [0.0]];
        yield 'modulo by zero' => [new Modulo(), 10, [0]];
        yield 'modulo without a divisor' => [new Modulo(), 10, []];
        yield 'zero to a negative power' => [new Power(), 0, [-1]];

        yield 'add to a non-numeric string' => [new Add(), 'abc', [5]];
        yield 'add a non-numeric argument' => [new Add(), 5, ['abc']];
        yield 'add to a partly numeric string' => [new Add(), '3abc', [5]];
        yield 'subtract from null' => [new Subtract(), null, [5]];
        yield 'multiply an array' => [new Multiply(), ['a'], [5]];
        yield 'divide an object' => [new Divide(), new \stdClass(), [5]];
        yield 'power of a non-numeric string' => [new Power(), 'abc', [2]];
    }

    /**
     * @param list<mixed> $arguments
     * @dataProvider provideBooleans
     */
    public function testBooleansCountAsOneAndZero(Mutator $mutator, mixed $value, array $arguments, mixed $expected): void
    {
        $this->assertSame($expected, $mutator->apply($value, $arguments));
    }

    /**
     * @return iterable<string, array{Mutator, mixed, list<mixed>, mixed}>
     */
    public static function provideBooleans(): iterable
    {
        yield 'true is one' => [new Add(), true, [1], 2];
        yield 'false is zero' => [new Add(), false, [1], 1];
        yield 'true as an argument' => [new Multiply(), 7, [true], 7];
    }

    public function testEveryArithmeticMutatorIsReachableByItsTemplateName(): void
    {
        $template = '{
            "attributes": [
                {"name": "Added", "path": ["n"], "mutators": [{"name": "add", "arguments": [5]}]},
                {"name": "Subtracted", "path": ["n"], "mutators": [{"name": "subtract", "arguments": [5]}]},
                {"name": "Multiplied", "path": ["n"], "mutators": [{"name": "multiply", "arguments": [5]}]},
                {"name": "Divided", "path": ["n"], "mutators": [{"name": "divide", "arguments": [5]}]},
                {"name": "Remainder", "path": ["n"], "mutators": [{"name": "modulo", "arguments": [5]}]},
                {"name": "Raised", "path": ["n"], "mutators": [{"name": "power", "arguments": [2]}]}
            ]
        }';

        $this->assertSame(
            [
                'Added' => 25,
                'Subtracted' => 15,
                'Multiplied' => 100,
                'Divided' => 4,
                'Remainder' => 0,
                'Raised' => 400,
            ],
            $this->mapper->map('{"n": 20}', $template),
        );
    }

    public function testArithmeticMutatorsChainInDeclarationOrder(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Total",
                    "path": ["price"],
                    "mutators": [
                        {"name": "multiply", "arguments": [100]},
                        {"name": "add", "arguments": [250]},
                        {"name": "divide", "arguments": [100]}
                    ]
                }
            ]
        }';

        $this->assertSame(['Total' => 43.0], $this->mapper->map('{"price": 40.5}', $template));
    }

    public function testArithmeticAppliesElementWiseToLists(): void
    {
        $template = '{
            "attributes": [{"name": "Cents", "path": ["prices"], "mutators": [{"name": "multiply", "arguments": [100]}]}]
        }';

        $this->assertSame(['Cents' => [1000, 2550.0]], $this->mapper->map('{"prices": [10, 25.5]}', $template));
    }

    public function testTheDefaultIsReturnedUnmutatedWhenThePathIsMissing(): void
    {
        $template = '{
            "attributes": [
                {"name": "Total", "path": ["missing"], "default": 7, "mutators": [{"name": "multiply", "arguments": [3]}]}
            ]
        }';

        $this->assertSame(['Total' => 7], $this->mapper->map('{}', $template));
    }
}
