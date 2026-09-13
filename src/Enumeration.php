<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

/**
 * The slice of PHP 8.1's enum API this library relies on, written for PHP 8.0.
 *
 * Cases are memoised per value, so `CastType::Date() === CastType::from('date')` holds
 * and identity comparisons behave exactly as they did while these types were enums.
 *
 * The composing class declares its cases as a VALUES constant and exposes one static
 * accessor per case.
 *
 * @internal
 */
trait Enumeration
{
    public string $value;

    /**
     * @var array<string, self>
     */
    private static array $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @return list<self>
     */
    public static function cases(): array
    {
        $cases = [];
        foreach (self::VALUES as $value) {
            $cases[] = self::of($value);
        }

        return $cases;
    }

    public static function from(string $value): self
    {
        $case = self::tryFrom($value);
        if ($case === null) {
            throw new \ValueError(sprintf('"%s" is not a valid value for %s.', $value, self::class));
        }

        return $case;
    }

    public static function tryFrom(string $value): ?self
    {
        return in_array($value, self::VALUES, true) ? self::of($value) : null;
    }

    private static function of(string $value): self
    {
        return self::$instances[$value] ??= new self($value);
    }
}
