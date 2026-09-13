<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;

/**
 * Which elements a "type": "array" attribute maps, in what order, and how many.
 */
final class ListSelectionTest extends TestCase
{
    private const ITEMS = '{"items": [
        {"sku": "A", "price": 30, "active": true},
        {"sku": "B", "price": 10, "active": false},
        {"sku": "C", "price": 20, "active": true}
    ]}';

    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    /**
     * @param array<string, mixed> $selection
     * @return list<string>
     */
    private function skus(array $selection, string $data = self::ITEMS): array
    {
        $mapped = $this->mapper->map($data, ['attributes' => [$selection + [
            'name' => 'Items',
            'type' => 'array',
            'path' => ['items'],
            'attributes' => [['name' => 'Sku', 'path' => ['sku']]],
        ]]]);

        return array_column($mapped['Items'], 'Sku');
    }

    public function testDropsElementsThatFailTheCondition(): void
    {
        $this->assertSame(
            ['A', 'C'],
            $this->skus(['where' => ['path' => ['active'], 'condition_type' => 'eq', 'value' => true]]),
        );
    }

    public function testEveryClauseMustHold(): void
    {
        $this->assertSame(
            ['A'],
            $this->skus(['where' => [
                ['path' => ['active'], 'condition_type' => 'eq', 'value' => true],
                ['path' => ['price'], 'condition_type' => 'gt', 'value' => 20],
            ]]),
        );
    }

    public function testAClauseWithoutAPathTestsTheElementItself(): void
    {
        $mapped = $this->mapper->map(
            '{"scores": [10, 55, 3, 20]}',
            '{"attributes": [{
                "name": "Scores",
                "type": "array",
                "path": ["scores"],
                "where": {"condition_type": "gte", "value": 20},
                "attributes": [{"name": "Value", "path": ["@"]}]
            }]}',
        );

        $this->assertSame(['Scores' => [['Value' => 55], ['Value' => 20]]], $mapped);
    }

    public function testKeepingNothingYieldsAnEmptyList(): void
    {
        $this->assertSame([], $this->skus(['where' => ['path' => ['sku'], 'condition_type' => 'eq', 'value' => 'Z']]));
    }

    public function testSortsByAValueInsideEachElement(): void
    {
        $this->assertSame(['B', 'C', 'A'], $this->skus(['sort' => ['path' => ['price']]]));
        $this->assertSame(['A', 'C', 'B'], $this->skus(['sort' => ['path' => ['price'], 'direction' => 'desc']]));
    }

    public function testWeighsSortKeysInTurn(): void
    {
        $data = '{"items": [
            {"sku": "A", "group": 2, "price": 10},
            {"sku": "B", "group": 1, "price": 30},
            {"sku": "C", "group": 1, "price": 20}
        ]}';

        $this->assertSame(
            ['C', 'B', 'A'],
            $this->skus(['sort' => [['path' => ['group']], ['path' => ['price']]]], $data),
        );
    }

    public function testElementsThatTieKeepThePayloadOrder(): void
    {
        $data = '{"items": [{"sku": "A", "price": 10}, {"sku": "B", "price": 10}, {"sku": "C", "price": 10}]}';

        $this->assertSame(['A', 'B', 'C'], $this->skus(['sort' => ['path' => ['price']]], $data));
    }

    public function testElementsWithoutTheSortKeyGatherAtOneEnd(): void
    {
        $data = '{"items": [{"sku": "A", "price": 10}, {"sku": "B"}, {"sku": "C", "price": 5}]}';

        $this->assertSame(['B', 'C', 'A'], $this->skus(['sort' => ['path' => ['price']]], $data));
    }

    public function testKeepsTheFirstOfEachDuplicate(): void
    {
        $data = '{"items": [
            {"sku": "A", "price": 10},
            {"sku": "A", "price": 99},
            {"sku": "B", "price": 20}
        ]}';

        $this->assertSame(['A', 'B'], $this->skus(['distinct' => ['sku']], $data));
    }

    public function testDeduplicatesWholeElements(): void
    {
        $data = '{"items": [{"sku": "A"}, {"sku": "A"}, {"sku": "A", "note": "different"}]}';

        $this->assertSame(['A', 'A'], $this->skus(['distinct' => true], $data));
    }

    public function testDeduplicatesOnSeveralPathsAtOnce(): void
    {
        $data = '{"items": [
            {"sku": "A", "size": "S"},
            {"sku": "A", "size": "M"},
            {"sku": "A", "size": "S"}
        ]}';

        $this->assertSame(['A', 'A'], $this->skus(['distinct' => [['sku'], ['size']]], $data));
    }

    public function testTellsAStringApartFromTheNumberThatLooksLikeIt(): void
    {
        $data = '{"items": [{"sku": "A", "id": 1}, {"sku": "B", "id": "1"}]}';

        $this->assertSame(['A', 'B'], $this->skus(['distinct' => [['id']]], $data));
    }

    public function testTakesAWindowOfTheResult(): void
    {
        $this->assertSame(['A', 'B'], $this->skus(['limit' => 2]));
        $this->assertSame(['B', 'C'], $this->skus(['offset' => 1]));
        $this->assertSame(['B'], $this->skus(['offset' => 1, 'limit' => 1]));
        $this->assertSame([], $this->skus(['limit' => 0]));
    }

    public function testANegativeOffsetCountsFromTheEnd(): void
    {
        $this->assertSame(['B', 'C'], $this->skus(['offset' => -2]));
    }

    public function testALimitBeyondTheEndIsNotAnError(): void
    {
        $this->assertSame(['A', 'B', 'C'], $this->skus(['limit' => 99]));
    }

    public function testNarrowsThenOrdersThenWindows(): void
    {
        $this->assertSame(
            ['A'],
            $this->skus([
                'where' => ['path' => ['active'], 'condition_type' => 'eq', 'value' => true],
                'sort' => ['path' => ['price'], 'direction' => 'desc'],
                'limit' => 1,
            ]),
        );
    }

    public function testSelectsInsideANestedList(): void
    {
        $data = '{"orders": [
            {"id": 1, "lines": [{"sku": "A", "qty": 0}, {"sku": "B", "qty": 3}]},
            {"id": 2, "lines": [{"sku": "C", "qty": 9}]}
        ]}';
        $template = '{"attributes": [{
            "name": "Orders",
            "type": "array",
            "path": ["orders"],
            "attributes": [
                {"name": "Id", "path": ["id"]},
                {
                    "name": "Lines",
                    "type": "array",
                    "path": ["lines"],
                    "where": {"path": ["qty"], "condition_type": "gt", "value": 0},
                    "attributes": [{"name": "Sku", "path": ["sku"]}]
                }
            ]
        }]}';

        $this->assertSame(
            ['Orders' => [
                ['Id' => 1, 'Lines' => [['Sku' => 'B']]],
                ['Id' => 2, 'Lines' => [['Sku' => 'C']]],
            ]],
            $this->mapper->map($data, $template),
        );
    }

    public function testDuplicatesAreWeighedInSortOrder(): void
    {
        $data = '{"items": [
            {"sku": "B", "price": 2},
            {"sku": "A", "price": 1},
            {"sku": "B", "price": 9}
        ]}';

        $mapped = $this->mapper->map($data, '{
            "type": "array",
            "path": ["items"],
            "sort": {"path": ["price"], "direction": "desc"},
            "distinct": ["sku"],
            "attributes": [{"name": "Sku", "path": ["sku"]}, {"name": "Price", "path": ["price"]}]
        }');

        // The duplicate that survives is the first one in sort order, not in payload order.
        $this->assertSame([['Sku' => 'B', 'Price' => 9], ['Sku' => 'A', 'Price' => 1]], $mapped);
    }

    public function testAnElementDroppedByWhereIsNeverMapped(): void
    {
        $data = '{"items": [{"sku": "A"}, {"note": "no sku, and never asked for one"}]}';
        $template = '{
            "type": "array",
            "path": ["items"],
            "where": {"path": ["sku"], "condition_type": "notnull"},
            "attributes": [{"name": "Sku", "path": ["sku"], "required": true}]
        }';

        $this->assertSame([['Sku' => 'A']], $this->mapper->map($data, $template));
    }

    public function testNarrowsAListReachedByAWildcard(): void
    {
        $data = '{"orders": [{"lines": [{"qty": 1}, {"qty": 9}]}, {"lines": [{"qty": 5}]}]}';
        $template = '{
            "type": "array",
            "path": ["orders", "*", "lines", "*"],
            "where": {"path": ["qty"], "condition_type": "gte", "value": 5},
            "attributes": [{"name": "Qty", "path": ["qty"]}]
        }';

        $this->assertSame([['Qty' => 9], ['Qty' => 5]], $this->mapper->map($data, $template));
    }

    public function testAListWithoutAPathMapsTheScopeItIsStandingOn(): void
    {
        $data = '{"orders": [{"lines": [{"sku": "A"}, {"sku": "B"}]}]}';
        $template = '{"attributes": [{
            "name": "Orders",
            "type": "array",
            "path": ["orders"],
            "attributes": [{
                "name": "Lines",
                "type": "array",
                "path": ["lines"],
                "attributes": [{"name": "Sku", "path": ["@"]}]
            }]
        }]}';

        $this->assertSame(
            ['Orders' => [['Lines' => [['Sku' => ['sku' => 'A']], ['Sku' => ['sku' => 'B']]]]]],
            $this->mapper->map($data, $template),
        );
    }
}
