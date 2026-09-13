<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Cast\CastStep;
use Zahran\Mapper\V2\Cast\CastType;
use Zahran\Mapper\V2\Condition\ConditionStep;
use Zahran\Mapper\V2\Exception\InvalidTemplateException;
use Zahran\Mapper\V2\Mutator\Mutator;
use Zahran\Mapper\V2\Mutator\MutatorStep;
use Zahran\Mapper\V2\Mutator\NativeFunction;
use Zahran\Mapper\V2\Node\ListNode;
use Zahran\Mapper\V2\Node\LiteralNode;
use Zahran\Mapper\V2\Node\Node;
use Zahran\Mapper\V2\Node\ObjectNode;
use Zahran\Mapper\V2\Node\ValueNode;

final class Compiler
{
    private const TYPE_ARRAY = 'array';

    /**
     * @var non-empty-list<string>
     */
    private const ATTRIBUTE_KEYS = ['name', 'type', 'path', 'paths', 'default', 'cast', 'conditions', 'mutators', 'attributes'];

    /**
     * @var non-empty-list<string>
     */
    private const CONDITION_KEYS = ['condition_type', 'value', 'then', 'otherwise'];

    /**
     * @var non-empty-list<string>
     */
    private const MUTATOR_KEYS = ['name', 'arguments'];

    /**
     * @var non-empty-list<string>
     */
    private const CAST_KEYS = ['type', 'format'];

    public function __construct(
        private Registry $registry,
    ) {
    }

    /**
     * @param array<array-key, mixed> $template
     */
    public function compile(array $template): ObjectNode
    {
        self::assertKeys($template, ['name', 'attributes'], '(root)');

        return new ObjectNode('root', $this->compileChildren($template['attributes'] ?? null, 'attributes'));
    }

    /**
     * @return non-empty-list<Node>
     */
    private function compileChildren(mixed $attributes, string $context): array
    {
        if (!is_array($attributes) || !Arr::isList($attributes) || $attributes === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of attributes.');
        }

        $nodes = [];
        foreach ($attributes as $index => $attribute) {
            $nodes[] = $this->compileNode($attribute, "{$context}.{$index}");
        }

        return $nodes;
    }

    private function compileNode(mixed $attribute, string $context): Node
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

        if ($type === self::TYPE_ARRAY) {
            return $this->compileList($attribute, $name, $context);
        }

        if (array_key_exists('attributes', $attribute)) {
            throw InvalidTemplateException::at("{$context}.attributes", sprintf('is only supported on attributes of type "%s".', self::TYPE_ARRAY));
        }

        if (array_key_exists('path', $attribute) && array_key_exists('paths', $attribute)) {
            throw InvalidTemplateException::at($context, 'must declare either a "path" or a "paths", never both.');
        }

        if (array_key_exists('paths', $attribute)) {
            return new ValueNode(
                $name,
                $this->compileGather($attribute['paths'], "{$context}.paths"),
                $attribute['default'] ?? null,
                $this->compilePipeline($attribute, $context, elementWise: false),
            );
        }

        $path = $attribute['path'] ?? null;
        if ($path === null) {
            return new LiteralNode($name, $this->compileLiteral($attribute, $context));
        }

        return new ValueNode(
            $name,
            $this->compilePath($path, "{$context}.path", allowLiterals: true),
            $attribute['default'] ?? null,
            $this->compilePipeline($attribute, $context),
        );
    }

    /**
     * @param array<array-key, mixed> $attribute
     */
    private function compileList(array $attribute, string $name, string $context): ListNode
    {
        foreach (['paths', 'default', 'cast', 'conditions', 'mutators'] as $key) {
            if (array_key_exists($key, $attribute)) {
                throw InvalidTemplateException::at(
                    "{$context}.{$key}",
                    sprintf('is not supported on attributes of type "%s" — declare it on a nested attribute instead.', self::TYPE_ARRAY),
                );
            }
        }

        return new ListNode(
            $name,
            $this->compilePath($attribute['path'] ?? null, "{$context}.path", allowLiterals: false),
            new ObjectNode($name, $this->compileChildren($attribute['attributes'] ?? null, "{$context}.attributes")),
        );
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
        foreach (['cast', 'conditions', 'mutators'] as $key) {
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

        $selection = null;
        if (is_array(Arr::last($path))) {
            $selection = $this->compileSelection(Arr::last($path), $context . '.' . array_key_last($path), $allowLiterals);
            array_pop($path);

            if ($path === []) {
                throw InvalidTemplateException::at($context, 'must declare the path to the source array before selecting indices.');
            }
        }

        $segments = [];
        foreach ($path as $index => $segment) {
            if (!is_string($segment) && !is_int($segment)) {
                throw InvalidTemplateException::at("{$context}.{$index}", 'must be a string or an integer.');
            }
            $segments[] = $segment;
        }

        return new Path($segments, $selection);
    }

    /**
     * @param array<array-key, mixed> $selection
     * @return non-empty-list<int|Literal>
     */
    private function compileSelection(array $selection, string $context, bool $allowLiterals): array
    {
        if (!Arr::isList($selection) || $selection === []) {
            throw InvalidTemplateException::at($context, 'must be a non-empty list of indices.');
        }

        $picks = [];
        foreach ($selection as $index => $pick) {
            if (is_int($pick)) {
                $picks[] = $pick;
                continue;
            }

            if (is_string($pick) && str_starts_with($pick, '$')) {
                if (!$allowLiterals) {
                    throw InvalidTemplateException::at(
                        "{$context}.{$index}",
                        'hard-coded values can only be appended when selecting values, not list items.',
                    );
                }
                $picks[] = new Literal(self::literalValue($pick));
                continue;
            }

            if (is_string($pick) && ctype_digit($pick)) {
                $picks[] = (int) $pick;
                continue;
            }

            throw InvalidTemplateException::at("{$context}.{$index}", 'must be an index or a "$"-prefixed hard-coded value.');
        }

        return $picks;
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

        return [new CastStep($resolved, $format)];
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
