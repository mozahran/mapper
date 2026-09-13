<?php

declare(strict_types=1);

namespace Zahran\Mapper\Cast;

use Zahran\Mapper\Enumeration;
use Zahran\Mapper\Text;

final class CastType implements Cast, Validating
{
    use Enumeration;

    public const BOOLEAN = 'boolean';
    public const DATE = 'date';
    public const INTEGER = 'integer';
    public const STRINGIFY = 'string';
    public const FLOATING_POINT_NUMBER = 'float';

    /**
     * @var non-empty-list<string>
     */
    public const VALUES = [
        self::BOOLEAN,
        self::DATE,
        self::INTEGER,
        self::STRINGIFY,
        self::FLOATING_POINT_NUMBER,
    ];

    public static function Boolean(): self
    {
        return self::of(self::BOOLEAN);
    }

    public static function Date(): self
    {
        return self::of(self::DATE);
    }

    public static function Integer(): self
    {
        return self::of(self::INTEGER);
    }

    public static function Stringify(): self
    {
        return self::of(self::STRINGIFY);
    }

    public static function FloatingPointNumber(): self
    {
        return self::of(self::FLOATING_POINT_NUMBER);
    }

    public function cast(mixed $value, ?string $format): mixed
    {
        return match ($this->value) {
            self::BOOLEAN => (bool) $value,
            self::INTEGER => (int) Text::of($value),
            self::FLOATING_POINT_NUMBER => (float) Text::of($value),
            self::STRINGIFY => Text::of($value),
            self::DATE => self::toDate($value, $format),
        };
    }

    /**
     * Whether the cast can carry this value over without inventing one.
     *
     * The bar is faithfulness, not convertibility: PHP will happily turn "abc" into 0
     * and the string "false" into true, and those are exactly the silent answers a
     * strict mapping is asking to be spared.
     */
    public function accepts(mixed $value): bool
    {
        return match ($this->value) {
            self::BOOLEAN => is_bool($value) || self::isOneOf($value, [0, 1, '0', '1']),
            self::INTEGER => is_bool($value) || self::isWholeNumber($value),
            self::FLOATING_POINT_NUMBER => is_bool($value) || is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            self::STRINGIFY => is_scalar($value) || $value instanceof \Stringable,
            self::DATE => (is_string($value) || $value instanceof \Stringable) && trim(Text::of($value)) !== '',
        };
    }

    /**
     * @param non-empty-list<int|string> $accepted
     */
    private static function isOneOf(mixed $value, array $accepted): bool
    {
        return (is_int($value) || is_string($value)) && in_array($value, $accepted, true);
    }

    private static function isWholeNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value) && $value == (int) $value;
        }

        return is_string($value) && is_numeric($value) && $value == (int) $value;
    }

    private static function toDate(mixed $value, ?string $format): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable(Text::of($value)))->format((string) $format);
    }
}
