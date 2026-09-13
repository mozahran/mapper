<?php

declare(strict_types=1);

namespace Zahran\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\Mapper;

final class GatherTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testCombinesSeveralPathsIntoOneValue(): void
    {
        $data = '{"first_name": "Ada", "last_name": "Lovelace"}';
        $template = '{
            "attributes": [
                {
                    "name": "fullName",
                    "paths": [["first_name"], ["last_name"]],
                    "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['fullName' => 'Ada Lovelace'], $this->mapper->map($data, $template));
    }

    public function testGathersIntoAListWhenNoMutatorCombinesIt(): void
    {
        $data = '{"first_name": "Ada", "last_name": "Lovelace"}';
        $template = '{"attributes": [{"name": "Name", "paths": [["first_name"], ["last_name"]]}]}';

        $this->assertSame(['Name' => ['Ada', 'Lovelace']], $this->mapper->map($data, $template));
    }

    public function testGathersASinglePathIntoAListOfOne(): void
    {
        $template = '{"attributes": [{"name": "Name", "paths": [["first_name"]]}]}';

        $this->assertSame(['Name' => ['Ada']], $this->mapper->map('{"first_name": "Ada"}', $template));
    }

    public function testReadsNestedPathsInEveryEntry(): void
    {
        $data = '{"order": {"customer": {"first": "Ada", "last": "Lovelace"}}}';
        $template = '{
            "attributes": [
                {
                    "name": "Customer",
                    "paths": [["order", "customer", "first"], ["order", "customer", "last"]],
                    "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['Customer' => 'Ada Lovelace'], $this->mapper->map($data, $template));
    }

    public function testSumsGatheredNumbers(): void
    {
        $data = '{"net": 40, "tax": 2.5}';
        $template = '{
            "attributes": [{"name": "Total", "paths": [["net"], ["tax"]], "mutators": [{"name": "array_sum"}]}]
        }';

        $this->assertSame(['Total' => 42.5], $this->mapper->map($data, $template));
    }

    public function testFormatsGatheredValuesWithVsprintf(): void
    {
        $data = '{"city": "Cairo", "country": "Egypt"}';
        $template = '{
            "attributes": [
                {
                    "name": "Location",
                    "paths": [["city"], ["country"]],
                    "mutators": [{"name": "vsprintf", "arguments": ["%s, %s", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['Location' => 'Cairo, Egypt'], $this->mapper->map($data, $template));
    }

    public function testAppendsHardCodedValuesBetweenGatheredPaths(): void
    {
        $data = '{"street": "1 Main St", "city": "Cairo"}';
        $template = '{
            "attributes": [
                {
                    "name": "Address",
                    "paths": [["street"], "$-", ["city"], "$EG"],
                    "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['Address' => '1 Main St - Cairo EG'], $this->mapper->map($data, $template));
    }

    public function testAMissingPathContributesNullAndKeepsTheOthersInPlace(): void
    {
        $data = '{"first": "Ada", "last": "Lovelace"}';
        $template = '{"attributes": [{"name": "Name", "paths": [["first"], ["middle"], ["last"]]}]}';

        $this->assertSame(['Name' => ['Ada', null, 'Lovelace']], $this->mapper->map($data, $template));
    }

    public function testUsesTheDefaultAndSkipsThePipelineWhenEveryPathIsMissing(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Name",
                    "paths": [["first_name"], ["last_name"]],
                    "default": "unknown",
                    "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                }
            ]
        }';

        $this->assertSame(['Name' => 'unknown'], $this->mapper->map('{}', $template));
        $this->assertSame(['Name' => 'Ada Lovelace'], $this->mapper->map('{"first_name": "Ada", "last_name": "Lovelace"}', $template));
    }

    public function testKeepsGatheringWhenOnlySomePathsAreMissing(): void
    {
        $template = '{
            "attributes": [{"name": "Name", "paths": [["first_name"], ["last_name"]], "default": "unknown"}]
        }';

        $this->assertSame(['Name' => ['Ada', null]], $this->mapper->map('{"first_name": "Ada"}', $template));
    }

    public function testLiteralsAloneNeverCountAsMissing(): void
    {
        $template = '{"attributes": [{"name": "Flags", "paths": ["$true", "$7"], "default": "unused"}]}';

        $this->assertSame(['Flags' => [true, 7]], $this->mapper->map('{}', $template));
    }

    public function testPipesTheWholeGatheredValueThroughMutatorsRatherThanEachItem(): void
    {
        $data = '{"first": "Ada", "last": "Lovelace"}';
        $template = '{"attributes": [{"name": "Parts", "paths": [["first"], ["last"]], "mutators": [{"name": "count"}]}]}';

        $this->assertSame(['Parts' => 2], $this->mapper->map($data, $template));
    }

    public function testConditionsSeeTheWholeGatheredValue(): void
    {
        $template = '{
            "attributes": [
                {
                    "name": "Kind",
                    "paths": [["first"], ["last"]],
                    "conditions": [{"condition_type": "contains", "value": "love", "then": "famous", "otherwise": "ordinary"}]
                }
            ]
        }';

        $this->assertSame(['Kind' => 'famous'], $this->mapper->map('{"first": "Ada", "last": "Lovelace"}', $template));
        $this->assertSame(['Kind' => 'ordinary'], $this->mapper->map('{"first": "Ada", "last": "Byron"}', $template));
    }

    public function testCastsTheCombinedValueAfterTheMutatorsHaveRun(): void
    {
        $data = '{"area": "20", "code": "25"}';
        $template = '{
            "attributes": [
                {
                    "name": "Dialling",
                    "paths": [["area"], ["code"]],
                    "mutators": [{"name": "implode", "arguments": ["", "__value__"]}],
                    "cast": {"type": "integer"}
                }
            ]
        }';

        $this->assertSame(['Dialling' => 2025], $this->mapper->map($data, $template));
    }

    public function testSelectsIndicesInsideAGatheredPath(): void
    {
        $data = '{"codes": [7, 9, 11], "label": "x"}';
        $template = '{"attributes": [{"name": "Picked", "paths": [["codes", [0, 1]], ["label"]]}]}';

        $this->assertSame(['Picked' => [[7, 9], 'x']], $this->mapper->map($data, $template));
    }

    public function testGathersInsideArrayItems(): void
    {
        $data = '{"people": [{"first": "Ada", "last": "Lovelace"}, {"first": "Alan", "last": "Turing"}]}';
        $template = '{
            "attributes": [
                {
                    "name": "People",
                    "type": "array",
                    "path": ["people"],
                    "attributes": [
                        {
                            "name": "Name",
                            "paths": [["first"], ["last"]],
                            "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                        }
                    ]
                }
            ]
        }';

        $this->assertSame(
            ['People' => [['Name' => 'Ada Lovelace'], ['Name' => 'Alan Turing']]],
            $this->mapper->map($data, $template),
        );
    }

    public function testAGatheringTemplateIsReusableAcrossPayloads(): void
    {
        $mapping = $this->mapper->compile('{
            "attributes": [
                {
                    "name": "Name",
                    "paths": [["first"], ["last"]],
                    "mutators": [{"name": "implode", "arguments": [" ", "__value__"]}]
                }
            ]
        }');

        $this->assertSame(
            [['Name' => 'Ada Lovelace'], ['Name' => 'Alan Turing']],
            iterator_to_array($mapping->mapMany([
                '{"first": "Ada", "last": "Lovelace"}',
                ['first' => 'Alan', 'last' => 'Turing'],
            ])),
        );
    }
}
