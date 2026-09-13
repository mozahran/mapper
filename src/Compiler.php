<?php

declare(strict_types=1);

namespace Zahran\Mapper;

use Zahran\Mapper\Cast\CastStep;
use Zahran\Mapper\Cast\CastType;
use Zahran\Mapper\Condition\Clause;
use Zahran\Mapper\Condition\ConditionStep;
use Zahran\Mapper\Exception\InvalidTemplateException;
use Zahran\Mapper\Mutator\Mutator;
use Zahran\Mapper\Mutator\MutatorStep;
use Zahran\Mapper\Mutator\NativeFunction;
use Zahran\Mapper\Node\ListNode;
use Zahran\Mapper\Node\LiteralNode;
use Zahran\Mapper\Node\Node;
use Zahran\Mapper\Node\ObjectNode;
use Zahran\Mapper\Node\ValueNode;
use Zahran\Mapper\Segment\Current;
use Zahran\Mapper\Segment\Descendants;
use Zahran\Mapper\Segment\Filter;
use Zahran\Mapper\Segment\Key;
use Zahran\Mapper\Segment\Segment;
use Zahran\Mapper\Segment\Wildcard;

final class Compiler
{
    private const TYPE_ARRAY = 'array';

    private const ROOT_NAME = 'root';

    private const DESCENDING = 'desc';

    /**
     * @var non-empty-list<string>
     */
    private const ATTRIBUTE_KEYS = [
        'name', 'type', 'path', 'paths', 'default', 'required',
        'cast', 'conditions', 'mutators', 'attributes',
        'where', 'sort', 'distinct', 'offset', 'limit',
    ];

    /**
     * The keys that only mean something when there is a list of elements to narrow.
     *
     * @var non-empty-list<string>
     */
    private const SELECTION_KEYS = ['where', 'sort', 'distinct', 'offset', 'limit'];

    /**
     * @var non-empty-list<string>
     */
    private const CONDITION_KEYS = ['condition_type', 'value', 'then', 'otherwise'];

    /**
     * @var non-empty-list<string>
     */
    private const CLAUSE_KEYS = ['path', 'condition_type', 'value'];

    /**
     * @var non-empty-list<string>
     */
    private const ORDER_KEYS = ['path', 'direction'];

    /**
     * @var non-empty-list<string>
     */
    private const MUTATOR_KEYS = ['name', 'arguments'];

    /**
     * @var non-empty-list<string>
     */
    private const CAST_KEYS = ['type', 'format'];

    /**
     * @param bool $strict whether casts refuse values they would have to invent an
     *                     answer for, and list attributes refuse a source that is not
     *                     a list, instead of quietly coercing either
     */
    public function __construct(
        private Registry $registry,
        private bool $strict = false,
    ) {
    }

    /**
     * The root is an attribute like any other: give it "attributes" and the mapping
     * yields an object, give it a "type": "array" and it yields a list, give it a bare
     * "path" and it yields whatever sits there.
     *
     * @param array<array-key, mixed> $template
     */
    public function compile(array $template): Node
    {
        if (!self::declaresAValue($template)) {
            self::assertKeys($template, ['name', 'attributes'], '(root)');

            return new ObjectNode(self::ROOT_NAME, $this->compileChildren($template['attributes'] ?? null, 'attributes', ''));
        }

        return $this->compileNode($template + ['name' => self::ROOT_NAME], '(root)', '');
    }

    /**
     * @param array<array-key, mixed> $template
     */
    private static function declaresAValue(array $template): bool
    {
        foreach (['type', 'path', 'paths', 'default'] as $key) {
            if (array_key_exists($key, $template)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $parent the dotted name of the attribute these sit under in the
     *                       output, which their payload errors are reported against
     * @return non-empty-list<Node>
     */
    private function compileChildren(mixed $attributes, string $context, string $parent): array
    {
        if (!is_array($attributes) || !Arr::isList($attributes) || $attributes === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of attributes.');
        }

        $nodes = [];
        foreach ($attributes as $index => $attribute) {
            $nodes[] = $this->compileNode($attribute, "{$context}.{$index}", $parent);
        }

        return $nodes;
    }

    private function compileNode(mixed $attribute, string $context, string $parent): Node
    {
        if (!is_array($attribute)) {
            throw InvalidTemplateException::at($context, 'must be an object.');
        }

        self::assertKeys($attribute, self::ATTRIBUTE_KEYS, $context);

        $name = $attribute['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw InvalidTemplateException::at("{$context}.name", 'must be a non-empty string.');
        }

        $type = $attribute['type'] ?? null;
        if ($type !== null && $type !== self::TYPE_ARRAY) {
            throw InvalidTemplateException::at("{$context}.type", sprintf('must be "%s" when present.', self::TYPE_ARRAY));
        }

        $output = $parent === '' ? $name : "{$parent}.{$name}";

        if ($type === self::TYPE_ARRAY) {
            return $this->compileList($attribute, $name, $context, $output);
        }

        if (array_key_exists('attributes', $attribute)) {
            throw InvalidTemplateException::at("{$context}.attributes", sprintf('is only supported on attributes of type "%s".', self::TYPE_ARRAY));
        }

        foreach (self::SELECTION_KEYS as $key) {
            if (array_key_exists($key, $attribute)) {
                throw InvalidTemplateException::at("{$context}.{$key}", sprintf('is only supported on attributes of type "%s".', self::TYPE_ARRAY));
            }
        }

        if (array_key_exists('path', $attribute) && array_key_exists('paths', $attribute)) {
            throw InvalidTemplateException::at($context, 'must declare either a "path" or a "paths", never both.');
        }

        if (array_key_exists('required', $attribute) && array_key_exists('default', $attribute)) {
            throw InvalidTemplateException::at("{$context}.required", 'cannot be combined with a "default", which already stands in for a missing value.');
        }

        if (array_key_exists('paths', $attribute)) {
            return new ValueNode(
                $name,
                $this->compileGather($attribute['paths'], "{$context}.paths"),
                $attribute['default'] ?? null,
                $this->compilePipeline($attribute, $context, elementWise: false),
                self::compileRequired($attribute, $context),
                $output,
            );
        }

        $path = $attribute['path'] ?? null;
        if ($path === null) {
            return new LiteralNode($name, $this->compileLiteral($attribute, $context));
        }

        $compiled = $this->compilePath($path, "{$context}.path", allowLiterals: true);

        return new ValueNode(
            $name,
            $compiled,
            self::compileDefault($attribute, $compiled),
            $this->compilePipeline($attribute, $context),
            self::compileRequired($attribute, $context),
            $output,
        );
    }

    /**
     * A path that can match many values is collection-shaped, so "nothing matched" reads
     * as an empty list rather than as null unless the template names another default.
     *
     * @param array<array-key, mixed> $attribute
     */
    private static function compileDefault(array $attribute, Path $path): mixed
    {
        if (array_key_exists('default', $attribute)) {
            return $attribute['default'];
        }

        return $path->isMultiValued() ? [] : null;
    }

    /**
     * @param array<array-key, mixed> $attribute
     */
    private function compileList(array $attribute, string $name, string $context, string $output): ListNode
    {
        foreach (['paths', 'default', 'cast', 'conditions', 'mutators'] as $key) {
            if (array_key_exists($key, $attribute)) {
                throw InvalidTemplateException::at(
                    "{$context}.{$key}",
                    sprintf('is not supported on attributes of type "%s" — declare it on a nested attribute instead.', self::TYPE_ARRAY),
                );
            }
        }

        // A list attribute without a path maps the scope it already stands on, which is
        // what a payload that is itself a list of items needs at the root.
        $path = array_key_exists('path', $attribute)
            ? $this->compilePath($attribute['path'], "{$context}.path", allowLiterals: false)
            : Path::identity();

        return new ListNode(
            $name,
            $path,
            new ObjectNode($name, $this->compileChildren($attribute['attributes'] ?? null, "{$context}.attributes", $output)),
            $this->compileSelection($attribute, $context),
            self::compileRequired($attribute, $context),
            $this->strict,
            $output,
        );
    }

    /**
     * @param array<array-key, mixed> $attribute
     */
    private static function compileRequired(array $attribute, string $context): bool
    {
        $required = $attribute['required'] ?? false;
        if (!is_bool($required)) {
            throw InvalidTemplateException::at("{$context}.required", 'must be a boolean.');
        }

        return $required;
    }

    /**
     * Everything that decides which elements a list attribute maps, and in what order.
     *
     * @param array<array-key, mixed> $attribute
     */
    private function compileSelection(array $attribute, string $context): ?Selection
    {
        $selection = new Selection(
            $this->compileWhere($attribute['where'] ?? null, "{$context}.where"),
            $this->compileSort($attribute['sort'] ?? null, "{$context}.sort"),
            $this->compileDistinct($attribute['distinct'] ?? null, "{$context}.distinct"),
            self::compileOffset($attribute['offset'] ?? null, "{$context}.offset"),
            self::compileLimit($attribute['limit'] ?? null, "{$context}.limit"),
        );

        return $selection->isEmpty() ? null : $selection;
    }

    /**
     * One clause, or a list of clauses that must all hold.
     *
     * @return list<Clause>
     */
    private function compileWhere(mixed $where, string $context): array
    {
        if ($where === null) {
            return [];
        }

        if (!is_array($where) || $where === []) {
            throw InvalidTemplateException::at($context, 'must be a clause or a non-empty list of clauses.');
        }

        $clauses = [];
        foreach (self::asList($where) as $index => $clause) {
            $itemContext = Arr::isList($where) ? "{$context}.{$index}" : $context;

            if (!is_array($clause) || Arr::isList($clause)) {
                throw InvalidTemplateException::at($itemContext, 'must be an object.');
            }

            self::assertKeys($clause, self::CLAUSE_KEYS, $itemContext);

            $type = $clause['condition_type'] ?? null;
            if (!is_string($type)) {
                throw InvalidTemplateException::at("{$itemContext}.condition_type", 'must be a string.');
            }

            $predicate = $this->registry->condition($type);
            if ($predicate === null) {
                throw InvalidTemplateException::at("{$itemContext}.condition_type", sprintf('"%s" is not a registered condition.', $type));
            }

            $clauses[] = new Clause(
                $predicate,
                $this->compileClausePath($clause, "{$itemContext}.path"),
                $clause['value'] ?? null,
            );
        }

        return $clauses;
    }

    /**
     * @return list<Order>
     */
    private function compileSort(mixed $sort, string $context): array
    {
        if ($sort === null) {
            return [];
        }

        if (!is_array($sort) || $sort === []) {
            throw InvalidTemplateException::at($context, 'must be a sort key or a non-empty list of sort keys.');
        }

        $orders = [];
        foreach (self::asList($sort) as $index => $key) {
            $itemContext = Arr::isList($sort) ? "{$context}.{$index}" : $context;

            if (!is_array($key) || Arr::isList($key)) {
                throw InvalidTemplateException::at($itemContext, 'must be an object.');
            }

            self::assertKeys($key, self::ORDER_KEYS, $itemContext);

            $direction = $key['direction'] ?? 'asc';
            if (!is_string($direction) || !in_array(strtolower($direction), ['asc', self::DESCENDING], true)) {
                throw InvalidTemplateException::at("{$itemContext}.direction", 'must be "asc" or "desc".');
            }

            $orders[] = new Order(
                $this->compileClausePath($key, "{$itemContext}.path"),
                strtolower($direction) === self::DESCENDING,
            );
        }

        return $orders;
    }

    /**
     * true to keep the first of each identical element, or the path — or paths — whose
     * values tell two elements apart.
     *
     * @return list<Path>|null
     */
    private function compileDistinct(mixed $distinct, string $context): ?array
    {
        if ($distinct === null || $distinct === false) {
            return null;
        }

        if ($distinct === true) {
            return [Path::identity()];
        }

        if (!is_array($distinct) || !Arr::isList($distinct) || $distinct === []) {
            throw InvalidTemplateException::at($context, 'must be true, a path, or a non-empty list of paths.');
        }

        if (!self::isListOfPaths($distinct)) {
            return [$this->compilePath($distinct, $context, allowLiterals: false)];
        }

        $paths = [];
        foreach ($distinct as $index => $path) {
            $paths[] = $this->compilePath($path, "{$context}.{$index}", allowLiterals: false);
        }

        return $paths;
    }

    /**
     * @param non-empty-list<mixed> $distinct
     */
    private static function isListOfPaths(array $distinct): bool
    {
        foreach ($distinct as $entry) {
            if (!is_array($entry)) {
                return false;
            }
        }

        return true;
    }

    private static function compileOffset(mixed $offset, string $context): int
    {
        if ($offset === null) {
            return 0;
        }

        if (!is_int($offset)) {
            throw InvalidTemplateException::at($context, 'must be an integer, negative to count from the end.');
        }

        return $offset;
    }

    private static function compileLimit(mixed $limit, string $context): ?int
    {
        if ($limit === null) {
            return null;
        }

        if (!is_int($limit) || $limit < 0) {
            throw InvalidTemplateException::at($context, 'must be a non-negative integer.');
        }

        return $limit;
    }

    /**
     * The path a clause or a sort key reads out of an element; absent, it reads the
     * element itself, which is what a list of scalars needs.
     *
     * @param array<array-key, mixed> $declaration
     */
    private function compileClausePath(array $declaration, string $context): Path
    {
        return array_key_exists('path', $declaration)
            ? $this->compilePath($declaration['path'], $context, allowLiterals: false)
            : Path::identity();
    }

    /**
     * @param array<array-key, mixed> $declaration
     * @return array<array-key, mixed>
     */
    private static function asList(array $declaration): array
    {
        return Arr::isList($declaration) ? $declaration : [$declaration];
    }

    /**
     * Compiles the sources an attribute gathers into one value: a path per entry, or a
     * "$"-prefixed hard-coded value to sit between them.
     */
    private function compileGather(mixed $paths, string $context): Gather
    {
        if (!is_array($paths) || !Arr::isList($paths) || $paths === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of paths.');
        }

        $sources = [];
        foreach ($paths as $index => $path) {
            if (is_string($path) && str_starts_with($path, '$')) {
                $sources[] = new Literal(self::literalValue($path));
                continue;
            }

            $sources[] = $this->compilePath($path, "{$context}.{$index}", allowLiterals: true);
        }

        return new Gather($sources);
    }

    /**
     * @param array<array-key, mixed> $attribute
     */
    private function compileLiteral(array $attribute, string $context): mixed
    {
        foreach (['cast', 'conditions', 'mutators', 'required'] as $key) {
            if (array_key_exists($key, $attribute)) {
                throw InvalidTemplateException::at("{$context}.{$key}", 'is not supported on attributes without a "path".');
            }
        }

        if (!array_key_exists('default', $attribute)) {
            throw InvalidTemplateException::at($context, 'must declare a "path", a "paths" or a "default".');
        }

        $default = $attribute['default'];

        return is_array($default) ? array_map(static fn (mixed $item): mixed => self::literalValue($item), $default) : $default;
    }

    private function compilePath(mixed $path, string $context, bool $allowLiterals): Path
    {
        if (!is_array($path) || !Arr::isList($path) || $path === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of path segments.');
        }

        // A trailing list is the fixed positions to read out of whatever the path
        // resolved to; a trailing object is a filter, and just another segment.
        $picks = null;
        $last = Arr::last($path);
        if (is_array($last) && Arr::isList($last)) {
            $picks = $this->compilePicks($last, $context . '.' . array_key_last($path), $allowLiterals);
            array_pop($path);

            if ($path === []) {
                throw InvalidTemplateException::at($context, 'must declare the path to the source array before selecting indices.');
            }
        }

        $segments = [];
        foreach ($path as $index => $segment) {
            $segments[] = $this->compileSegment($segment, "{$context}.{$index}");
        }

        return new Path($segments, $picks);
    }

    private function compileSegment(mixed $segment, string $context): Segment
    {
        if ($segment === Current::TOKEN) {
            return new Current();
        }

        if ($segment === Wildcard::TOKEN) {
            return new Wildcard();
        }

        if ($segment === Descendants::TOKEN) {
            return new Descendants();
        }

        if (is_string($segment) || is_int($segment)) {
            return new Key($segment);
        }

        if (is_array($segment) && !Arr::isList($segment)) {
            self::assertKeys($segment, [Filter::KEY], $context);

            $clauses = $this->compileWhere($segment[Filter::KEY] ?? null, "{$context}." . Filter::KEY);
            if ($clauses === []) {
                throw InvalidTemplateException::at("{$context}." . Filter::KEY, 'must be a clause or a non-empty list of clauses.');
            }

            return new Filter($clauses);
        }

        throw InvalidTemplateException::at(
            $context,
            sprintf(
                'must be a key, "%s" for the value itself, "%s" for every value, "%s" for every descendant, or a {"%s": …} filter.',
                Current::TOKEN,
                Wildcard::TOKEN,
                Descendants::TOKEN,
                Filter::KEY,
            ),
        );
    }

    /**
     * @param array<array-key, mixed> $picks
     * @return non-empty-list<int|Literal>
     */
    private function compilePicks(array $picks, string $context, bool $allowLiterals): array
    {
        if ($picks === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of indices.');
        }

        $compiled = [];
        foreach ($picks as $index => $pick) {
            if (is_int($pick)) {
                $compiled[] = $pick;
                continue;
            }

            if (is_string($pick) && str_starts_with($pick, '$')) {
                if (!$allowLiterals) {
                    throw InvalidTemplateException::at(
                        "{$context}.{$index}",
                        'hard-coded values can only be appended when selecting values, not list items.',
                    );
                }
                $compiled[] = new Literal(self::literalValue($pick));
                continue;
            }

            if (is_string($pick) && ctype_digit($pick)) {
                $compiled[] = (int) $pick;
                continue;
            }

            throw InvalidTemplateException::at("{$context}.{$index}", 'must be an index or a "$"-prefixed hard-coded value.');
        }

        return $compiled;
    }

    /**
     * @param array<array-key, mixed> $attribute
     */
    private function compilePipeline(array $attribute, string $context, bool $elementWise = true): ?Pipeline
    {
        $steps = array_merge(
            $this->compileConditions($attribute['conditions'] ?? null, "{$context}.conditions"),
            $this->compileMutators($attribute['mutators'] ?? null, "{$context}.mutators"),
            $this->compileCast($attribute['cast'] ?? null, "{$context}.cast"),
        );

        return $steps === [] ? null : new Pipeline($steps, $elementWise);
    }

    /**
     * @return list<ConditionStep>
     */
    private function compileConditions(mixed $conditions, string $context): array
    {
        if ($conditions === null) {
            return [];
        }

        if (!is_array($conditions) || !Arr::isList($conditions)) {
            throw InvalidTemplateException::at($context, 'must be a list of conditions.');
        }

        $steps = [];
        foreach ($conditions as $index => $condition) {
            $itemContext = "{$context}.{$index}";

            if (!is_array($condition)) {
                throw InvalidTemplateException::at($itemContext, 'must be an object.');
            }

            self::assertKeys($condition, self::CONDITION_KEYS, $itemContext);

            $type = $condition['condition_type'] ?? null;
            if (!is_string($type)) {
                throw InvalidTemplateException::at("{$itemContext}.condition_type", 'must be a string.');
            }

            $predicate = $this->registry->condition($type);
            if ($predicate === null) {
                throw InvalidTemplateException::at("{$itemContext}.condition_type", sprintf('"%s" is not a registered condition.', $type));
            }

            if (!array_key_exists('then', $condition)) {
                throw InvalidTemplateException::at("{$itemContext}.then", 'is required.');
            }

            $steps[] = new ConditionStep(
                $predicate,
                $condition['value'] ?? null,
                $condition['then'],
                $condition['otherwise'] ?? null,
            );
        }

        return $steps;
    }

    /**
     * @return list<MutatorStep>
     */
    private function compileMutators(mixed $mutators, string $context): array
    {
        if ($mutators === null) {
            return [];
        }

        if (!is_array($mutators) || !Arr::isList($mutators)) {
            throw InvalidTemplateException::at($context, 'must be a list of mutators.');
        }

        $steps = [];
        foreach ($mutators as $index => $mutator) {
            $itemContext = "{$context}.{$index}";

            if (!is_array($mutator)) {
                throw InvalidTemplateException::at($itemContext, 'must be an object.');
            }

            self::assertKeys($mutator, self::MUTATOR_KEYS, $itemContext);

            $name = $mutator['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw InvalidTemplateException::at("{$itemContext}.name", 'must be a non-empty string.');
            }

            $arguments = $mutator['arguments'] ?? [];
            if (!is_array($arguments) || !Arr::isList($arguments)) {
                throw InvalidTemplateException::at("{$itemContext}.arguments", 'must be a list.');
            }

            $steps[] = new MutatorStep($this->resolveMutator($name, "{$itemContext}.name"), $arguments);
        }

        return $steps;
    }

    private function resolveMutator(string $name, string $context): Mutator
    {
        $mutator = $this->registry->mutator($name);
        if ($mutator !== null) {
            return $mutator;
        }

        if ($this->registry->allowsFunction($name)) {
            return new NativeFunction($name);
        }

        throw InvalidTemplateException::at(
            $context,
            sprintf('"%s" is neither a registered mutator nor an allowed PHP function.', $name),
        );
    }

    /**
     * @return list<CastStep>
     */
    private function compileCast(mixed $cast, string $context): array
    {
        if ($cast === null) {
            return [];
        }

        if (!is_array($cast)) {
            throw InvalidTemplateException::at($context, 'must be an object.');
        }

        self::assertKeys($cast, self::CAST_KEYS, $context);

        $type = $cast['type'] ?? null;
        if (!is_string($type)) {
            throw InvalidTemplateException::at("{$context}.type", 'must be a string.');
        }

        $resolved = $this->registry->cast($type);
        if ($resolved === null) {
            throw InvalidTemplateException::at("{$context}.type", sprintf('"%s" is not a registered cast type.', $type));
        }

        $format = $cast['format'] ?? null;
        if ($format !== null && !is_string($format)) {
            throw InvalidTemplateException::at("{$context}.format", 'must be a string.');
        }

        if ($resolved === CastType::Date() && ($format === null || $format === '')) {
            throw InvalidTemplateException::at("{$context}.format", sprintf('is required when casting to "%s".', CastType::Date()->value));
        }

        return [new CastStep($resolved, $type, $format, $this->strict)];
    }

    private static function literalValue(mixed $value): mixed
    {
        if (!is_string($value) || !str_starts_with($value, '$')) {
            return $value;
        }

        $literal = substr($value, 1);

        return match (strtolower($literal)) {
            'null' => null,
            'true' => true,
            'false' => false,
            default => is_numeric($literal) ? $literal + 0 : $literal,
        };
    }

    /**
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string> $allowed
     */
    private static function assertKeys(array $data, array $allowed, string $context): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw InvalidTemplateException::at(
                    $context,
                    sprintf('"%s" is not a supported key here, expected one of: %s.', $key, implode(', ', $allowed)),
                );
            }
        }
    }
}
