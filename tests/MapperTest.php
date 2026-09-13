<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;

final class MapperTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testMapsObjectAttributes(): void
    {
        $data = '{"item": "Skirt", "price": "40"}';
        $template = '{
            "name": "root",
            "attributes": [
                {"name": "ItemName", "path": ["item"]},
                {"name": "ItemPrice", "path": ["price"]}
            ]
        }';

        $this->assertSame(
            ['ItemName' => 'Skirt', 'ItemPrice' => '40'],
            $this->mapper->map($data, $template),
        );
    }

    public function testMapsNestedPaths(): void
    {
        $data = '{"order": {"customer": {"name": "Ada"}}}';
        $template = '{"attributes": [{"name": "Customer", "path": ["order", "customer", "name"]}]}';

        $this->assertSame(['Customer' => 'Ada'], $this->mapper->map($data, $template));
    }

    public function testMapsArrays(): void
    {
        $data = '{"items": [{"name": "Skirt"}, {"name": "T-Shirt"}]}';
        $template = '{
            "attributes": [
                {
                    "name": "ItemsArray",
                    "type": "array",
                    "path": ["items"],
                    "attributes": [{"name": "ItemName", "path": ["name"]}]
                }
            ]
        }';

        $this->assertSame(
            ['ItemsArray' => [['ItemName' => 'Skirt'], ['ItemName' => 'T-Shirt']]],
            $this->mapper->map($data, $template),
        );
    }

    public function testMapsNestedArrays(): void
    {
        $data = '{
            "orders": [
                {"id": 1, "lines": [{"sku": "A"}, {"sku": "B"}]},
                {"id": 2, "lines": [{"sku": "C"}]}
            ]
        }';
        $template = '{
            "attributes": [
                {
                    "name": "Orders",
                    "type": "array",
                    "path": ["orders"],
                    "attributes": [
                        {"name": "Id", "path": ["id"]},
                        {
                            "name": "Lines",
                            "type": "array",
                            "path": ["lines"],
                            "attributes": [{"name": "Sku", "path": ["sku"]}]
                        }
                    ]
                }
            ]
        }';

        $this->assertSame(
            [
                'Orders' => [
                    ['Id' => 1, 'Lines' => [['Sku' => 'A'], ['Sku' => 'B']]],
                    ['Id' => 2, 'Lines' => [['Sku' => 'C']]],
                ],
            ],
            $this->mapper->map($data, $template),
        );
    }

    public function testYieldsAnEmptyListWhenTheArraySourceIsNotAnArray(): void
    {
        $template = '{
            "attributes": [
                {"name": "Items", "type": "array", "path": ["items"], "attributes": [{"name": "Sku", "path": ["sku"]}]}
            ]
        }';

        $this->assertSame(['Items' => []], $this->mapper->map('{"items": "nope"}', $template));
        $this->assertSame(['Items' => []], $this->mapper->map('{}', $template));
    }

    public function testUsesDefaultWhenThePathIsMissing(): void
    {
        $template = '{"attributes": [{"name": "PersonName", "path": ["fullname"], "default": "John Doe"}]}';

        $this->assertSame(['PersonName' => 'John Doe'], $this->mapper->map('{}', $template));
    }

    public function testDistinguishesAMissingKeyFromAnExplicitNull(): void
    {
        $template = '{"attributes": [{"name": "Value", "path": ["value"], "default": "fallback"}]}';

        $this->assertSame(['Value' => 'fallback'], $this->mapper->map('{}', $template));
        $this->assertSame(['Value' => null], $this->mapper->map('{"value": null}', $template));
    }

    public function testSkipsThePipelineWhenThePathIsMissing(): void
    {
        $template = '{
            "attributes": [
                {"name": "Name", "path": ["name"], "default": "fallback", "mutators": [{"name": "strtoupper"}]}
            ]
        }';

        $this->assertSame(['Name' => 'fallback'], $this->mapper->map('{}', $template));
        $this->assertSame(['Name' => 'ADA'], $this->mapper->map('{"name": "Ada"}', $template));
    }

    public function testInjectsHardCodedValuesForAttributesWithoutAPath(): void
    {
        $template = '{"attributes": [{"name": "Flags", "default": ["$true", "$null", "$7", "plain"]}]}';

        $this->assertSame(['Flags' => [true, null, 7, 'plain']], $this->mapper->map('{}', $template));
    }

    public function testIgnoresThePayloadForLiteralAttributes(): void
    {
        $template = '{"attributes": [{"name": "Source", "default": "static"}]}';

        $this->assertSame(['Source' => 'static'], $this->mapper->map('{"Source": "dynamic"}', $template));
    }

    public function testSelectsIndicesFromTheResolvedArray(): void
    {
        $data = '{"categories": [10, 55, 3, 20]}';
        $template = '{"attributes": [{"name": "categories", "path": ["categories", [0, 1]]}]}';

        $this->assertSame(['categories' => [10, 55]], $this->mapper->map($data, $template));
    }

    public function testAppendsHardCodedValuesToASelection(): void
    {
        $data = '{"categories": [10, 55, 3, 20]}';
        $template = '{
            "attributes": [
                {"name": "categories", "path": ["categories", [0, 1, "$foo", "$100.5", "$100", "$true", "$false", "$null"]]}
            ]
        }';

        $this->assertSame(
            ['categories' => [10, 55, 'foo', 100.5, 100, true, false, null]],
            $this->mapper->map($data, $template),
        );
    }

    public function testFillsASelectionWithNullWhenTheSourceIsMissing(): void
    {
        $template = '{"attributes": [{"name": "categories", "path": ["categories", [0, 1, "$kept"]]}]}';

        $this->assertSame(['categories' => [null, null, 'kept']], $this->mapper->map('{}', $template));
    }

    public function testAcceptsDigitStringsAsSelectionIndices(): void
    {
        $data = '{"categories": [10, 55, 3]}';
        $template = '{"attributes": [{"name": "categories", "path": ["categories", ["2"]]}]}';

        $this->assertSame(['categories' => [3]], $this->mapper->map($data, $template));
    }

    public function testReadsIntegerPathSegments(): void
    {
        $data = '{"rows": [{"id": 7}]}';
        $template = '{"attributes": [{"name": "First", "path": ["rows", 0, "id"]}]}';

        $this->assertSame(['First' => 7], $this->mapper->map($data, $template));
    }

    public function testAcceptsAlreadyDecodedPayloadsAndTemplates(): void
    {
        $mapped = $this->mapper->map(
            ['item' => 'Skirt'],
            ['attributes' => [['name' => 'ItemName', 'path' => ['item']]]],
        );

        $this->assertSame(['ItemName' => 'Skirt'], $mapped);
    }

    public function testCompiledMappingIsReusable(): void
    {
        $mapping = $this->mapper->compile('{"attributes": [{"name": "Name", "path": ["name"]}]}');

        $this->assertSame(['Name' => 'Ada'], $mapping->map('{"name": "Ada"}'));
        $this->assertSame(['Name' => 'Linus'], $mapping->map('{"name": "Linus"}'));
    }

    public function testMapManyIsLazyAndPreservesKeys(): void
    {
        $mapping = $this->mapper->compile('{"attributes": [{"name": "Name", "path": ["name"]}]}');

        $mapped = $mapping->mapMany(['a' => '{"name": "Ada"}', 'b' => ['name' => 'Linus']]);

        $this->assertInstanceOf(\Generator::class, $mapped);
        $this->assertSame(['a' => ['Name' => 'Ada'], 'b' => ['Name' => 'Linus']], iterator_to_array($mapped));
    }
}
