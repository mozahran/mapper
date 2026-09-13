<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;

final class PipelineTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testAppliesConditions(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Status",
                    "path": ["completed"],
                    "conditions": [{"condition_type": "eq", "value": true, "then": "done", "otherwise": "pending"}]
                }
            ]
        }';

        $this->assertSame(['Status' => 'done'], $this->mapper->map('{"completed": true}', $template));
        $this->assertSame(['Status' => 'pending'], $this->mapper->map('{"completed": false}', $template));
    }

    public function testAConditionWithoutOtherwiseKeepsTheOriginalValue(): void
    {
        $template = '{
            "attributes": [
                {"name": "Size", "path": ["size"], "conditions": [{"condition_type": "eq", "value": "S", "then": "Small"}]}
            ]
        }';

        $this->assertSame(['Size' => 'Small'], $this->mapper->map('{"size": "S"}', $template));
        $this->assertSame(['Size' => 'XL'], $this->mapper->map('{"size": "XL"}', $template));
    }

    public function testConditionsRunInDeclarationOrderAndFeedEachOther(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Grade",
                    "path": ["score"],
                    "conditions": [
                        {"condition_type": "gte", "value": 90, "then": "A"},
                        {"condition_type": "eq", "value": "A", "then": "excellent"}
                    ]
                }
            ]
        }';

        $this->assertSame(['Grade' => 'excellent'], $this->mapper->map('{"score": 95}', $template));
    }

    public function testAppliesNativeFunctionMutators(): void
    {
        $template = '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "strtoupper"}]}]}';

        $this->assertSame(['Name' => 'ADA'], $this->mapper->map('{"name": "Ada"}', $template));
    }

    public function testSubstitutesTheValuePlaceholderIntoMutatorArguments(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Slug",
                    "path": ["title"],
                    "mutators": [{"name": "str_replace", "arguments": [" ", "-", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['Slug' => 'hello-world'], $this->mapper->map('{"title": "hello world"}', $template));
    }

    public function testAppliesRegisteredMutators(): void
    {
        $template = '{
            "attributes": [{"name": "Views", "path": ["views"], "mutators": [{"name": "multiply", "arguments": [5]}]}]
        }';

        $this->assertSame(['Views' => 50], $this->mapper->map('{"views": 10}', $template));
    }

    public function testMutatorsRunInDeclarationOrder(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Name",
                    "path": ["name"],
                    "mutators": [
                        {"name": "trim"},
                        {"name": "strtoupper"},
                        {"name": "substr", "arguments": ["__value__", 0, 3]}
                    ]
                }
            ]
        }';

        $this->assertSame(['Name' => 'ADA'], $this->mapper->map('{"name": "  adalovelace  "}', $template));
    }

    public function testAppliesCasts(): void
    {
        $template = '{"attributes": [{"name": "Price", "path": ["price"], "cast": {"type": "float"}}]}';

        $this->assertSame(['Price' => 40.5], $this->mapper->map('{"price": "40.5"}', $template));
    }

    public function testStepsRunAsConditionsThenMutatorsThenCastRegardlessOfKeyOrder(): void
    {
        // Written cast-first: if order followed the template, the cast to integer would
        // run before multiply and the condition would never see the raw string.
        $template = '{
            "attributes": [
                {
                    "name": "Total",
                    "path": ["qty"],
                    "cast": {"type": "string"},
                    "mutators": [{"name": "multiply", "arguments": [3]}],
                    "conditions": [{"condition_type": "is_string", "value": null, "then": 10}]
                }
            ]
        }';

        $this->assertSame(['Total' => '30'], $this->mapper->map('{"qty": "2"}', $template));
    }

    public function testPipelineStepsApplyElementWiseToArrayValues(): void
    {
        $template = '{"attributes": [{"name": "Tags", "path": ["tags"], "mutators": [{"name": "strtoupper"}]}]}';

        $this->assertSame(['Tags' => ['RED', 'GREEN']], $this->mapper->map('{"tags": ["red", "green"]}', $template));
    }

    public function testPipelineStepsApplyElementWiseToSelections(): void
    {
        $template = '{
            "attributes": [{"name": "Codes", "path": ["codes", [0, 1]], "cast": {"type": "integer"}}]
        }';

        $this->assertSame(['Codes' => [7, 9]], $this->mapper->map('{"codes": ["7", "9", "11"]}', $template));
    }

    public function testRunsThePipelineInsideArrayItems(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Items",
                    "type": "array",
                    "path": ["items"],
                    "attributes": [
                        {"name": "Name", "path": ["name"], "mutators": [{"name": "strtoupper"}]},
                        {"name": "Price", "path": ["price"], "cast": {"type": "integer"}}
                    ]
                }
            ]
        }';

        $this->assertSame(
            ['Items' => [['Name' => 'SKIRT', 'Price' => 40], ['Name' => 'SHIRT', 'Price' => 50]]],
            $this->mapper->map('{"items": [{"name": "skirt", "price": "40"}, {"name": "shirt", "price": "50"}]}', $template),
        );
    }
}
