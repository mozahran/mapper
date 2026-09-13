<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Exception\InvalidJsonException;
use Zahran\Mapper\V2\Exception\InvalidTemplateException;
use Zahran\Mapper\V2\Exception\MappingException;
use Zahran\Mapper\V2\Mapper;

final class TemplateValidationTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    /**
     * @dataProvider provideInvalidTemplates
     */
    public function testRejectsInvalidTemplates(string $template, string $expectedMessage): void
    {
        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->mapper->compile($template);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidTemplates(): iterable
    {
        yield 'unknown root key' => [
            '{"version": 2, "attributes": [{"name": "A", "default": 1}]}',
            'at "(root)": "version" is not a supported key here',
        ];
        yield 'missing attributes' => [
            '{"name": "root"}',
            'at "attributes": must be a non-empty list of attributes.',
        ];
        yield 'empty attributes' => [
            '{"attributes": []}',
            'at "attributes": must be a non-empty list of attributes.',
        ];
        yield 'attributes keyed as an object' => [
            '{"attributes": {"a": {"name": "A", "default": 1}}}',
            'at "attributes": must be a non-empty list of attributes.',
        ];
        yield 'attribute is not an object' => [
            '{"attributes": ["nope"]}',
            'at "attributes.0": must be an object.',
        ];
        yield 'unknown attribute key' => [
            '{"attributes": [{"name": "A", "path": ["a"], "transform": "x"}]}',
            'at "attributes.0": "transform" is not a supported key here',
        ];
        yield 'missing name' => [
            '{"attributes": [{"path": ["a"]}]}',
            'at "attributes.0.name": must be a non-empty string.',
        ];
        yield 'empty name' => [
            '{"attributes": [{"name": "", "path": ["a"]}]}',
            'at "attributes.0.name": must be a non-empty string.',
        ];
        yield 'unsupported type' => [
            '{"attributes": [{"name": "A", "type": "object", "path": ["a"]}]}',
            'at "attributes.0.type": must be "array" when present.',
        ];
        yield 'nested attributes without the array type' => [
            '{"attributes": [{"name": "A", "path": ["a"], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.attributes": is only supported on attributes of type "array".',
        ];
        yield 'default on an array attribute' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "default": [], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.default": is not supported on attributes of type "array"',
        ];
        yield 'cast on an array attribute' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "cast": {"type": "string"}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.cast": is not supported on attributes of type "array"',
        ];
        yield 'neither a path nor a default' => [
            '{"attributes": [{"name": "A"}]}',
            'at "attributes.0": must declare a "path", a "paths" or a "default".',
        ];
        yield 'both a path and paths' => [
            '{"attributes": [{"name": "A", "path": ["a"], "paths": [["b"], ["c"]]}]}',
            'at "attributes.0": must declare either a "path" or a "paths", never both.',
        ];
        yield 'empty paths' => [
            '{"attributes": [{"name": "A", "paths": []}]}',
            'at "attributes.0.paths": must be a non-empty list of paths.',
        ];
        yield 'paths that is not a list' => [
            '{"attributes": [{"name": "A", "paths": {"first": ["a"]}}]}',
            'at "attributes.0.paths": must be a non-empty list of paths.',
        ];
        yield 'a gathered entry that is not a path' => [
            '{"attributes": [{"name": "A", "paths": [["a"], "b"]}]}',
            'at "attributes.0.paths.1": must be a non-empty list of path segments.',
        ];
        yield 'an empty gathered path' => [
            '{"attributes": [{"name": "A", "paths": [["a"], []]}]}',
            'at "attributes.0.paths.1": must be a non-empty list of path segments.',
        ];
        yield 'a gathered segment of an unknown kind' => [
            '{"attributes": [{"name": "A", "paths": [["a"], [true]]}]}',
            'at "attributes.0.paths.1.0": must be a key, "@" for the value itself, "*" for every value, "**" for every descendant, or a {"where": …} filter.',
        ];
        yield 'paths on an array attribute' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "paths": [["b"]], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.paths": is not supported on attributes of type "array"',
        ];
        yield 'mutators without a path' => [
            '{"attributes": [{"name": "A", "default": "x", "mutators": [{"name": "trim"}]}]}',
            'at "attributes.0.mutators": is not supported on attributes without a "path".',
        ];
        yield 'conditions without a path' => [
            '{"attributes": [{"name": "A", "default": "x", "conditions": []}]}',
            'at "attributes.0.conditions": is not supported on attributes without a "path".',
        ];
        yield 'empty path' => [
            '{"attributes": [{"name": "A", "path": []}]}',
            'at "attributes.0.path": must be a non-empty list of path segments.',
        ];
        yield 'path segment of an unknown kind' => [
            '{"attributes": [{"name": "A", "path": [true]}]}',
            'at "attributes.0.path.0": must be a key, "@" for the value itself, "*" for every value, "**" for every descendant, or a {"where": …} filter.',
        ];
        yield 'selection with no source path' => [
            '{"attributes": [{"name": "A", "path": [[0, 1]]}]}',
            'must declare the path to the source array before selecting indices.',
        ];
        yield 'empty selection' => [
            '{"attributes": [{"name": "A", "path": ["a", []]}]}',
            'must be a non-empty list of indices.',
        ];
        yield 'non-index selection entry' => [
            '{"attributes": [{"name": "A", "path": ["a", ["first"]]}]}',
            'must be an index or a "$"-prefixed hard-coded value.',
        ];
        yield 'hard-coded value in a list path' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a", ["$x"]], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'hard-coded values can only be appended when selecting values, not list items.',
        ];
        yield 'conditions keyed as an object' => [
            '{"attributes": [{"name": "A", "path": ["a"], "conditions": {"a": 1}}]}',
            'at "attributes.0.conditions": must be a list of conditions.',
        ];
        yield 'unknown condition key' => [
            '{"attributes": [{"name": "A", "path": ["a"], "conditions": [{"condition_type": "eq", "then": 1, "else": 2}]}]}',
            'at "attributes.0.conditions.0": "else" is not a supported key here',
        ];
        yield 'unregistered condition' => [
            '{"attributes": [{"name": "A", "path": ["a"], "conditions": [{"condition_type": "matches", "then": 1}]}]}',
            '"matches" is not a registered condition.',
        ];
        yield 'condition without then' => [
            '{"attributes": [{"name": "A", "path": ["a"], "conditions": [{"condition_type": "eq", "value": 1}]}]}',
            'at "attributes.0.conditions.0.then": is required.',
        ];
        yield 'mutators keyed as an object' => [
            '{"attributes": [{"name": "A", "path": ["a"], "mutators": {"name": "trim"}}]}',
            'at "attributes.0.mutators": must be a list of mutators.',
        ];
        yield 'mutator without a name' => [
            '{"attributes": [{"name": "A", "path": ["a"], "mutators": [{"arguments": []}]}]}',
            'at "attributes.0.mutators.0.name": must be a non-empty string.',
        ];
        yield 'mutator arguments keyed as an object' => [
            '{"attributes": [{"name": "A", "path": ["a"], "mutators": [{"name": "trim", "arguments": {"a": 1}}]}]}',
            'at "attributes.0.mutators.0.arguments": must be a list.',
        ];
        yield 'unregistered mutator' => [
            '{"attributes": [{"name": "A", "path": ["a"], "mutators": [{"name": "shell_exec"}]}]}',
            '"shell_exec" is neither a registered mutator nor an allowed PHP function.',
        ];
        yield 'cast as a scalar' => [
            '{"attributes": [{"name": "A", "path": ["a"], "cast": "integer"}]}',
            'at "attributes.0.cast": must be an object.',
        ];
        yield 'unknown cast key' => [
            '{"attributes": [{"name": "A", "path": ["a"], "cast": {"type": "date", "pattern": "Y"}}]}',
            'at "attributes.0.cast": "pattern" is not a supported key here',
        ];
        yield 'unregistered cast type' => [
            '{"attributes": [{"name": "A", "path": ["a"], "cast": {"type": "decimal"}}]}',
            '"decimal" is not a registered cast type.',
        ];
        yield 'non-string cast format' => [
            '{"attributes": [{"name": "A", "path": ["a"], "cast": {"type": "string", "format": 5}}]}',
            'at "attributes.0.cast.format": must be a string.',
        ];
        yield 'where on a value attribute' => [
            '{"attributes": [{"name": "A", "path": ["a"], "where": {"condition_type": "notnull"}}]}',
            'at "attributes.0.where": is only supported on attributes of type "array".',
        ];
        yield 'limit on a value attribute' => [
            '{"attributes": [{"name": "A", "path": ["a"], "limit": 1}]}',
            'at "attributes.0.limit": is only supported on attributes of type "array".',
        ];
        yield 'required alongside a default' => [
            '{"attributes": [{"name": "A", "path": ["a"], "required": true, "default": "x"}]}',
            'at "attributes.0.required": cannot be combined with a "default", which already stands in for a missing value.',
        ];
        yield 'required without a path' => [
            '{"attributes": [{"name": "A", "required": true}]}',
            'at "attributes.0.required": is not supported on attributes without a "path".',
        ];
        yield 'required that is not a boolean' => [
            '{"attributes": [{"name": "A", "path": ["a"], "required": "yes"}]}',
            'at "attributes.0.required": must be a boolean.',
        ];
        yield 'an empty where' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": [], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where": must be a clause or a non-empty list of clauses.',
        ];
        yield 'a clause that is not an object' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": ["nope"], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where.0": must be an object.',
        ];
        yield 'unknown clause key' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": {"condition_type": "eq", "then": 1}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where": "then" is not a supported key here',
        ];
        yield 'a clause without a condition type' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": {"path": ["b"]}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where.condition_type": must be a string.',
        ];
        yield 'a clause with an unregistered condition' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": {"condition_type": "matches"}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where.condition_type": "matches" is not a registered condition.',
        ];
        yield 'an empty sort' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "sort": [], "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.sort": must be a sort key or a non-empty list of sort keys.',
        ];
        yield 'unknown sort key' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "sort": {"path": ["b"], "order": "desc"}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.sort": "order" is not a supported key here',
        ];
        yield 'an unknown sort direction' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "sort": {"path": ["b"], "direction": "up"}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.sort.direction": must be "asc" or "desc".',
        ];
        yield 'a distinct that is neither true nor a path' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "distinct": "sku", "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.distinct": must be true, a path, or a non-empty list of paths.',
        ];
        yield 'an offset that is not an integer' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "offset": "1", "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.offset": must be an integer, negative to count from the end.',
        ];
        yield 'a negative limit' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "limit": -1, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.limit": must be a non-negative integer.',
        ];
        yield 'unknown key in a filter segment' => [
            '{"attributes": [{"name": "A", "path": ["items", {"filter": {"condition_type": "notnull"}}, "sku"]}]}',
            'at "attributes.0.path.1": "filter" is not a supported key here, expected one of: where.',
        ];
        yield 'a filter segment with nothing to filter by' => [
            '{"attributes": [{"name": "A", "path": ["items", {"where": null}, "sku"]}]}',
            'at "attributes.0.path.1.where": must be a clause or a non-empty list of clauses.',
        ];
        yield 'an unusable path inside a clause' => [
            '{"attributes": [{"name": "A", "type": "array", "path": ["a"], "where": {"path": [], "condition_type": "notnull"}, "attributes": [{"name": "B", "path": ["b"]}]}]}',
            'at "attributes.0.where.path": must be a non-empty list of path segments.',
        ];
        yield 'date cast without a format' => [
            '{"attributes": [{"name": "A", "path": ["a"], "cast": {"type": "date"}}]}',
            'at "attributes.0.cast.format": is required when casting to "date".',
        ];
    }

    public function testReportsTheDottedPathOfANestedFailure(): void
    {
        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage('at "attributes.1.attributes.0.name": must be a non-empty string.');

        $this->mapper->compile('{
            "attributes": [
                {"name": "Ok", "path": ["ok"]},
                {"name": "Items", "type": "array", "path": ["items"], "attributes": [{"path": ["sku"]}]}
            ]
        }');
    }

    public function testRejectsUndecodableTemplateJson(): void
    {
        $this->expectException(InvalidJsonException::class);
        $this->expectExceptionMessage('The mappings JSON could not be decoded');

        $this->mapper->compile('{not json');
    }

    public function testRejectsTemplateJsonThatIsNotAStructure(): void
    {
        $this->expectException(InvalidJsonException::class);
        $this->expectExceptionMessage('The mappings JSON must decode to an object or an array, got string.');

        $this->mapper->compile('"nope"');
    }

    public function testRejectsUndecodablePayloadJson(): void
    {
        $mapping = $this->mapper->compile('{"attributes": [{"name": "A", "path": ["a"]}]}');

        $this->expectException(InvalidJsonException::class);
        $this->expectExceptionMessage('The data JSON could not be decoded');

        $mapping->map('{not json');
    }

    public function testEveryTemplateErrorIsCatchableAsAMappingException(): void
    {
        $this->expectException(MappingException::class);

        $this->mapper->compile('{"attributes": [{"name": "A"}]}');
    }

    public function testEveryJsonErrorIsCatchableAsAMappingException(): void
    {
        $this->expectException(MappingException::class);

        $this->mapper->compile('{not json');
    }
}
