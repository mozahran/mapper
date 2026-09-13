<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;

/**
 * The root is an attribute like any other, so a mapping is not forced to end in an
 * object keyed by attribute name.
 */
final class RootShapeTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testARootOfTypeArrayYieldsATopLevelList(): void
    {
        $data = '{"items": [{"sku": "A"}, {"sku": "B"}]}';
        $template = '{
            "type": "array",
            "path": ["items"],
            "attributes": [{"name": "Sku", "path": ["sku"]}]
        }';

        $this->assertSame([['Sku' => 'A'], ['Sku' => 'B']], $this->mapper->map($data, $template));
    }

    public function testARootListWithoutAPathMapsThePayloadItself(): void
    {
        $data = '[{"sku": "A"}, {"sku": "B"}]';
        $template = '{"type": "array", "attributes": [{"name": "Sku", "path": ["sku"]}]}';

        $this->assertSame([['Sku' => 'A'], ['Sku' => 'B']], $this->mapper->map($data, $template));
    }

    public function testARootListNarrowsLikeAnyOther(): void
    {
        $data = '[{"sku": "A", "active": false}, {"sku": "B", "active": true}]';
        $template = '{
            "type": "array",
            "where": {"path": ["active"], "condition_type": "eq", "value": true},
            "attributes": [{"name": "Sku", "path": ["sku"]}]
        }';

        $this->assertSame([['Sku' => 'B']], $this->mapper->map($data, $template));
    }

    public function testARootPathYieldsABareValue(): void
    {
        $data = '{"order": {"id": 7}}';

        $this->assertSame(7, $this->mapper->map($data, '{"path": ["order", "id"]}'));
    }

    public function testABareRootValueIsPipedLikeAnyOther(): void
    {
        $data = '{"order": {"total": "40.5"}}';
        $template = '{"path": ["order", "total"], "cast": {"type": "float"}}';

        $this->assertSame(40.5, $this->mapper->map($data, $template));
    }

    public function testARootPathCanBeMultiValued(): void
    {
        $data = '{"items": [{"sku": "A"}, {"sku": "B"}]}';

        $this->assertSame(['A', 'B'], $this->mapper->map($data, '{"path": ["items", "*", "sku"]}'));
    }

    public function testARootDefaultYieldsAHardCodedValue(): void
    {
        $this->assertSame('static', $this->mapper->map('{}', '{"default": "static"}'));
    }

    public function testARootOfGatheredPathsYieldsOneValue(): void
    {
        $data = '{"first": "Ada", "last": "Lovelace"}';
        $template = '{
            "paths": [["first"], "$ ", ["last"]],
            "mutators": [{"name": "implode", "arguments": ["", "__value__"]}]
        }';

        $this->assertSame('Ada Lovelace', $this->mapper->map($data, $template));
    }

    public function testAnObjectRootIsUnchanged(): void
    {
        $template = '{"name": "root", "attributes": [{"name": "Sku", "path": ["sku"]}]}';

        $this->assertSame(['Sku' => 'A'], $this->mapper->map('{"sku": "A"}', $template));
    }

    public function testMapManyCarriesTheRootShapeThrough(): void
    {
        $mapping = $this->mapper->compile('{"path": ["name"]}');

        $this->assertSame(
            ['Ada', 'Linus'],
            iterator_to_array($mapping->mapMany(['{"name": "Ada"}', ['name' => 'Linus']])),
        );
    }
}
