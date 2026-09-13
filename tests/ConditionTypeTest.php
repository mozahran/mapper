<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Condition\ConditionType;

final class ConditionTypeTest extends TestCase
{
    /**
     * @dataProvider provideMatches
     */
    public function testMatches(ConditionType $type, mixed $value, mixed $compare, bool $expected): void
    {
        $this->assertSame($expected, $type->matches($value, $compare));
    }

    /**
     * @return iterable<string, array{ConditionType, mixed, mixed, bool}>
     */
    public static function provideMatches(): iterable
    {
        yield 'contains is a case-insensitive substring test' => [ConditionType::Contains(), 'Hello World', 'hello', true];
        yield 'contains misses' => [ConditionType::Contains(), 'Hello World', 'bye', false];
        yield 'contains requires every needle' => [ConditionType::Contains(), 'Hello World', ['hello', 'bye'], false];
        yield 'contains across all needles' => [ConditionType::Contains(), 'Hello World', ['hello', 'world'], true];
        yield 'contains searches every haystack' => [ConditionType::Contains(), ['red', 'green'], 'gree', true];
        yield 'missing is the negation of contains' => [ConditionType::Missing(), 'Hello World', 'bye', true];
        yield 'missing when present' => [ConditionType::Missing(), 'Hello World', 'hello', false];

        yield 'eq is loose' => [ConditionType::Equals(), '1', 1, true];
        yield 'eq matches identical values' => [ConditionType::Equals(), 'a', 'a', true];
        yield 'eq misses' => [ConditionType::Equals(), 'a', 'b', false];
        yield 'neq is strict' => [ConditionType::NotEquals(), '1', 1, true];
        yield 'neq on identical values' => [ConditionType::NotEquals(), 'a', 'a', false];

        yield 'gt' => [ConditionType::GreaterThan(), 10, 5, true];
        yield 'gt on equal values' => [ConditionType::GreaterThan(), 5, 5, false];
        yield 'gte on equal values' => [ConditionType::GreaterThanOrEquals(), 5, 5, true];
        yield 'lt' => [ConditionType::LessThan(), 3, 5, true];
        yield 'lte on equal values' => [ConditionType::LessThanOrEquals(), 5, 5, true];

        yield 'in a list' => [ConditionType::Inset(), 'b', ['a', 'b'], true];
        yield 'in a comma separated string' => [ConditionType::Inset(), 'b', 'a,b,c', true];
        yield 'in misses' => [ConditionType::Inset(), 'z', 'a,b,c', false];
        yield 'not_in' => [ConditionType::NotInset(), 'z', ['a', 'b'], true];

        yield 'null' => [ConditionType::Nullable(), null, null, true];
        yield 'null on an empty string' => [ConditionType::Nullable(), '', null, false];
        yield 'notnull' => [ConditionType::NotNullable(), 0, null, true];
        yield 'notnull on null' => [ConditionType::NotNullable(), null, null, false];

        yield 'is_numeric on a numeric string' => [ConditionType::IsNumeric(), '40', null, true];
        yield 'is_numeric on a word' => [ConditionType::IsNumeric(), 'forty', null, false];
        yield 'is_string' => [ConditionType::IsString(), 'a', null, true];
        yield 'is_string on an integer' => [ConditionType::IsString(), 1, null, false];
        yield 'is_boolean' => [ConditionType::IsBoolean(), false, null, true];
        yield 'is_float' => [ConditionType::IsFloat(), 1.5, null, true];
        yield 'is_double is an alias of is_float' => [ConditionType::IsDouble(), 1.5, null, true];
        yield 'is_float on an integer' => [ConditionType::IsFloat(), 1, null, false];
    }

    public function testEveryConditionTypeIsReachableByItsTemplateName(): void
    {
        $names = array_map(static fn (ConditionType $type): string => $type->value, ConditionType::cases());

        $this->assertSame(
            [
                'contains', 'missing', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte',
                'in', 'not_in', 'null', 'notnull',
                'is_numeric', 'is_string', 'is_boolean', 'is_float', 'is_double',
            ],
            $names,
        );
    }
}
