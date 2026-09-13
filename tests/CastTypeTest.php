<?php

declare(strict_types=1);

namespace Zahran\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\Cast\CastType;

final class CastTypeTest extends TestCase
{
    /**
     * @dataProvider provideCasts
     */
    public function testCasts(CastType $type, mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, $type->cast($value, null));
    }

    /**
     * @return iterable<string, array{CastType, mixed, mixed}>
     */
    public static function provideCasts(): iterable
    {
        yield 'boolean from a truthy string' => [CastType::Boolean(), 'yes', true];
        yield 'boolean from "0"' => [CastType::Boolean(), '0', false];
        yield 'boolean from an empty string' => [CastType::Boolean(), '', false];
        yield 'boolean from null' => [CastType::Boolean(), null, false];
        yield 'boolean from an empty list' => [CastType::Boolean(), [], false];
        yield 'boolean from a non-empty list' => [CastType::Boolean(), [0], true];

        yield 'integer from a numeric string' => [CastType::Integer(), '40', 40];
        yield 'integer from a float string' => [CastType::Integer(), '40.9', 40];
        yield 'integer from a float' => [CastType::Integer(), 40.9, 40];
        yield 'integer from null' => [CastType::Integer(), null, 0];
        yield 'integer from true' => [CastType::Integer(), true, 1];
        yield 'integer from an array' => [CastType::Integer(), ['a'], 0];

        yield 'float from a numeric string' => [CastType::FloatingPointNumber(), '40.5', 40.5];
        yield 'float from an integer' => [CastType::FloatingPointNumber(), 40, 40.0];
        yield 'float from null' => [CastType::FloatingPointNumber(), null, 0.0];

        yield 'string from an integer' => [CastType::Stringify(), 40, '40'];
        yield 'string from a float' => [CastType::Stringify(), 40.5, '40.5'];
        yield 'string from true' => [CastType::Stringify(), true, '1'];
        yield 'string from false' => [CastType::Stringify(), false, ''];
        yield 'string from null' => [CastType::Stringify(), null, ''];
        yield 'string from an array' => [CastType::Stringify(), ['a'], ''];
    }

    public function testCastsStringableObjectsToString(): void
    {
        $value = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        $this->assertSame('stringable', CastType::Stringify()->cast($value, null));
    }

    public function testCastsDatesWithTheGivenFormat(): void
    {
        $this->assertSame('2024-03-05', CastType::Date()->cast('2024-03-05 14:30:00', 'Y-m-d'));
        $this->assertSame('05/03/2024', CastType::Date()->cast('2024-03-05', 'd/m/Y'));
    }

    public function testCastsEmptyDatesToNull(): void
    {
        $this->assertNull(CastType::Date()->cast(null, 'Y-m-d'));
        $this->assertNull(CastType::Date()->cast('', 'Y-m-d'));
    }

    public function testEveryCastTypeIsReachableByItsTemplateName(): void
    {
        $names = array_map(static fn (CastType $type): string => $type->value, CastType::cases());

        $this->assertSame(['boolean', 'date', 'integer', 'string', 'float'], $names);
    }
}
