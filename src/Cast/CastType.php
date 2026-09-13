<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Cast;

use Zahran\Mapper\V2\Enumeration;
use Zahran\Mapper\V2\Text;

final class CastType implements Cast
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

    private static function toDate(mixed $value, ?string $format): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable(Text::of($value)))->format((string) $format);
    }
}
