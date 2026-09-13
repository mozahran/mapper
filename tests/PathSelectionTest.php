<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Mapper;

/**
 * Paths that resolve by content rather than by position: wildcards, recursive descent,
 * indices counted from the end, and {"where": …} filters.
 */
final class PathSelectionTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testReadsEveryValueOneLevelDown(): void
    {
        $data = '{"items": [{"sku": "A"}, {"sku": "B"}]}';
        $template = '{"attributes": [{"name": "Skus", "path": ["items", "*", "sku"]}]}';

        $this->assertSame(['Skus' => ['A', 'B']], $this->mapper->map($data, $template));
    }

    public function testAWildcardWalksTheValuesOfAMapAsWellAsAList(): void
    {
        $data = '{"prices": {"skirt": 40, "shirt": 30}}';
        $template = '{"attributes": [{"name": "Prices", "path": ["prices", "*"]}]}';

        $this->assertSame(['Prices' => [40, 30]], $this->mapper->map($data, $template));
    }

    public function testAWildcardSkipsElementsThatLackTheKey(): void
    {
        $data = '{"items": [{"sku": "A"}, {"name": "no sku here"}, {"sku": "C"}]}';
        $template = '{"attributes": [{"name": "Skus", "path": ["items", "*", "sku"]}]}';

        $this->assertSame(['Skus' => ['A', 'C']], $this->mapper->map($data, $template));
    }

    public function testWildcardsNest(): void
    {
        $data = '{"orders": [{"lines": [{"sku": "A"}, {"sku": "B"}]}, {"lines": [{"sku": "C"}]}]}';
        $template = '{"attributes": [{"name": "Skus", "path": ["orders", "*", "lines", "*", "sku"]}]}';

        $this->assertSame(['Skus' => ['A', 'B', 'C']], $this->mapper->map($data, $template));
    }

    public function testReadsEveryDescendantHoweverDeep(): void
    {
        $data = '{"order": {"lines": [{"sku": "A"}], "gift": {"sku": "B"}}}';
        $template = '{"attributes": [{"name": "Skus", "path": ["**", "sku"]}]}';

        $this->assertSame(['Skus' => ['A', 'B']], $this->mapper->map($data, $template));
    }

    public function testDescentCanStartPartWayDownThePayload(): void
    {
        $data = '{"kept": {"id": 1}, "dropped": {"id": 2}}';
        $template = '{"attributes": [{"name": "Ids", "path": ["kept", "**", "id"]}]}';

        $this->assertSame(['Ids' => [1]], $this->mapper->map($data, $template));
    }

    public function testAMultiValuedPathYieldsAnEmptyListWhenNothingMatches(): void
    {
        $template = '{"attributes": [{"name": "Skus", "path": ["items", "*", "sku"]}]}';

        $this->assertSame(['Skus' => []], $this->mapper->map('{}', $template));
        $this->assertSame(['Skus' => []], $this->mapper->map('{"items": []}', $template));
    }

    public function testAMultiValuedPathStillHonoursADeclaredDefault(): void
    {
        $template = '{"attributes": [{"name": "Skus", "path": ["items", "*", "sku"], "default": "none"}]}';

        $this->assertSame(['Skus' => 'none'], $this->mapper->map('{}', $template));
    }

    public function testReadsAnIndexFromTheEnd(): void
    {
        $data = '{"rows": [{"id": 1}, {"id": 2}, {"id": 3}]}';

        $this->assertSame(
            ['Last' => 3, 'SecondToLast' => 2],
            $this->mapper->map($data, '{"attributes": [
                {"name": "Last", "path": ["rows", -1, "id"]},
                {"name": "SecondToLast", "path": ["rows", -2, "id"]}
            ]}'),
        );
    }

    public function testAnIndexPastTheStartOfTheListIsMissing(): void
    {
        $data = '{"rows": [{"id": 1}]}';
        $template = '{"attributes": [{"name": "Row", "path": ["rows", -2, "id"], "default": "none"}]}';

        $this->assertSame(['Row' => 'none'], $this->mapper->map($data, $template));
    }

    public function testALiteralNegativeKeyStillWins(): void
    {
        $data = '{"scores": {"-1": "literal", "0": "first"}}';
        $template = '{"attributes": [{"name": "Score", "path": ["scores", -1]}]}';

        $this->assertSame(['Score' => 'literal'], $this->mapper->map($data, $template));
    }

    public function testFiltersElementsByAValueInsideThem(): void
    {
        $data = '{"items": [
            {"sku": "A", "active": true},
            {"sku": "B", "active": false},
            {"sku": "C", "active": true}
        ]}';
        $template = '{"attributes": [
            {"name": "Skus", "path": ["items", {"where": {"path": ["active"], "condition_type": "eq", "value": true}}, "sku"]}
        ]}';

        $this->assertSame(['Skus' => ['A', 'C']], $this->mapper->map($data, $template));
    }

    public function testAFilterWithoutAPathTestsTheElementItself(): void
    {
        $data = '{"tags": ["red", "green", "blue"]}';
        $template = '{"attributes": [
            {"name": "Tags", "path": ["tags", {"where": {"condition_type": "contains", "value": "e"}}]}
        ]}';

        $this->assertSame(['Tags' => ['red', 'green', 'blue']], $this->mapper->map($data, $template));
    }

    public function testEveryClauseOfAFilterMustHold(): void
    {
        $data = '{"items": [
            {"sku": "A", "active": true, "stock": 0},
            {"sku": "B", "active": true, "stock": 5},
            {"sku": "C", "active": false, "stock": 9}
        ]}';
        $template = '{"attributes": [
            {"name": "Skus", "path": ["items", {"where": [
                {"path": ["active"], "condition_type": "eq", "value": true},
                {"path": ["stock"], "condition_type": "gt", "value": 0}
            ]}, "sku"]}
        ]}';

        $this->assertSame(['Skus' => ['B']], $this->mapper->map($data, $template));
    }

    public function testAFilterReadsAKeyTheElementLacksAsNull(): void
    {
        $data = '{"items": [{"sku": "A", "discount": 10}, {"sku": "B"}]}';
        $template = '{"attributes": [
            {"name": "Skus", "path": ["items", {"where": {"path": ["discount"], "condition_type": "notnull"}}, "sku"]}
        ]}';

        $this->assertSame(['Skus' => ['A']], $this->mapper->map($data, $template));
    }

    public function testFiltersCombineWithWildcardsAndDescent(): void
    {
        $data = '{"orders": [
            {"lines": [{"sku": "A", "qty": 1}, {"sku": "B", "qty": 4}]},
            {"lines": [{"sku": "C", "qty": 7}]}
        ]}';
        $template = '{"attributes": [
            {"name": "Skus", "path": ["**", "lines", {"where": {"path": ["qty"], "condition_type": "gte", "value": 4}}, "sku"]}
        ]}';

        $this->assertSame(['Skus' => ['B', 'C']], $this->mapper->map($data, $template));
    }

    public function testAMultiValuedPathFeedsTheAttributePipeline(): void
    {
        $data = '{"items": [{"sku": "a"}, {"sku": "b"}]}';
        $template = '{"attributes": [
            {"name": "Skus", "path": ["items", "*", "sku"], "mutators": [{"name": "strtoupper"}]}
        ]}';

        $this->assertSame(['Skus' => ['A', 'B']], $this->mapper->map($data, $template));
    }

    public function testAMultiValuedPathCanSourceAListAttribute(): void
    {
        $data = '{"orders": [{"lines": [{"sku": "A"}]}, {"lines": [{"sku": "B"}]}]}';
        $template = '{"attributes": [
            {
                "name": "Lines",
                "type": "array",
                "path": ["orders", "*", "lines", "*"],
                "attributes": [{"name": "Sku", "path": ["sku"]}]
            }
        ]}';

        $this->assertSame(['Lines' => [['Sku' => 'A'], ['Sku' => 'B']]], $this->mapper->map($data, $template));
    }

    public function testAMultiValuedPathIsGatheredAsASingleEntry(): void
    {
        $data = '{"id": 7, "items": [{"sku": "A"}, {"sku": "B"}]}';
        $template = '{"attributes": [{"name": "Summary", "paths": [["id"], ["items", "*", "sku"]]}]}';

        $this->assertSame(['Summary' => [7, ['A', 'B']]], $this->mapper->map($data, $template));
    }

    public function testAGatheredMultiValuedPathThatMatchesNothingContributesNull(): void
    {
        $template = '{"attributes": [{"name": "Summary", "paths": [["id"], ["items", "*", "sku"]]}]}';

        $this->assertSame(['Summary' => [7, null]], $this->mapper->map('{"id": 7}', $template));
    }

    public function testPositionsCanStillBeSelectedOutOfAMultiValuedPath(): void
    {
        $data = '{"items": [{"sku": "A"}, {"sku": "B"}, {"sku": "C"}]}';
        $template = '{"attributes": [{"name": "Skus", "path": ["items", "*", "sku", [0, 2]]}]}';

        $this->assertSame(['Skus' => ['A', 'C']], $this->mapper->map($data, $template));
    }
}
