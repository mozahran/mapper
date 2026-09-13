<?php

declare(strict_types=1);

namespace Zahran\Mapper\Condition;

use Zahran\Mapper\Enumeration;
use Zahran\Mapper\Text;

final class ConditionType implements Predicate
{
    use Enumeration;

    public const CONTAINS = 'contains';
    public const MISSING = 'missing';
    public const EQUALS = 'eq';
    public const NOT_EQUALS = 'neq';
    public const GREATER_THAN = 'gt';
    public const GREATER_THAN_OR_EQUALS = 'gte';
    public const LESS_THAN = 'lt';
    public const LESS_THAN_OR_EQUALS = 'lte';
    public const INSET = 'in';
    public const NOT_INSET = 'not_in';
    public const NULLABLE = 'null';
    public const NOT_NULLABLE = 'notnull';
    public const IS_NUMERIC = 'is_numeric';
    public const IS_STRING = 'is_string';
    public const IS_BOOLEAN = 'is_boolean';
    public const IS_FLOAT = 'is_float';
    public const IS_DOUBLE = 'is_double';

    /**
     * @var non-empty-list<string>
     */
    public const VALUES = [
        self::CONTAINS,
        self::MISSING,
        self::EQUALS,
        self::NOT_EQUALS,
        self::GREATER_THAN,
        self::GREATER_THAN_OR_EQUALS,
        self::LESS_THAN,
        self::LESS_THAN_OR_EQUALS,
        self::INSET,
        self::NOT_INSET,
        self::NULLABLE,
        self::NOT_NULLABLE,
        self::IS_NUMERIC,
        self::IS_STRING,
        self::IS_BOOLEAN,
        self::IS_FLOAT,
        self::IS_DOUBLE,
    ];

    public static function Contains(): self
    {
        return self::of(self::CONTAINS);
    }

    public static function Missing(): self
    {
        return self::of(self::MISSING);
    }

    public static function Equals(): self
    {
        return self::of(self::EQUALS);
    }

    public static function NotEquals(): self
    {
        return self::of(self::NOT_EQUALS);
    }

    public static function GreaterThan(): self
    {
        return self::of(self::GREATER_THAN);
    }

    public static function GreaterThanOrEquals(): self
    {
        return self::of(self::GREATER_THAN_OR_EQUALS);
    }

    public static function LessThan(): self
    {
        return self::of(self::LESS_THAN);
    }

    public static function LessThanOrEquals(): self
    {
        return self::of(self::LESS_THAN_OR_EQUALS);
    }

    public static function Inset(): self
    {
        return self::of(self::INSET);
    }

    public static function NotInset(): self
    {
        return self::of(self::NOT_INSET);
    }

    public static function Nullable(): self
    {
        return self::of(self::NULLABLE);
    }

    public static function NotNullable(): self
    {
        return self::of(self::NOT_NULLABLE);
    }

    public static function IsNumeric(): self
    {
        return self::of(self::IS_NUMERIC);
    }

    public static function IsString(): self
    {
        return self::of(self::IS_STRING);
    }

    public static function IsBoolean(): self
    {
        return self::of(self::IS_BOOLEAN);
    }

    public static function IsFloat(): self
    {
        return self::of(self::IS_FLOAT);
    }

    public static function IsDouble(): self
    {
        return self::of(self::IS_DOUBLE);
    }

    public function matches(mixed $value, mixed $compare): bool
    {
        return match ($this->value) {
            self::CONTAINS => self::containsEvery($value, $compare),
            self::MISSING => !self::containsEvery($value, $compare),
            self::EQUALS => $value == $compare,
            self::NOT_EQUALS => $value !== $compare,
            self::GREATER_THAN => $value > $compare,
            self::GREATER_THAN_OR_EQUALS => $value >= $compare,
            self::LESS_THAN => $value < $compare,
            self::LESS_THAN_OR_EQUALS => $value <= $compare,
            self::INSET => self::isInSet($value, $compare),
            self::NOT_INSET => !self::isInSet($value, $compare),
            self::NULLABLE => $value === null,
            self::NOT_NULLABLE => $value !== null,
            self::IS_NUMERIC => is_numeric($value),
            self::IS_STRING => is_string($value),
            self::IS_BOOLEAN => is_bool($value),
            self::IS_FLOAT, self::IS_DOUBLE => is_float($value),
        };
    }

    private static function containsEvery(mixed $value, mixed $compare): bool
    {
        $haystacks = is_array($value) ? $value : [$value];
        $needles = is_array($compare) ? $compare : [$compare];

        foreach ($needles as $needle) {
            $found = false;
            foreach ($haystacks as $haystack) {
                if (stripos(Text::of($haystack), Text::of($needle)) !== false) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    private static function isInSet(mixed $value, mixed $compare): bool
    {
        $set = is_array($compare) ? $compare : explode(',', Text::of($compare));

        return in_array($value, $set);
    }
}
