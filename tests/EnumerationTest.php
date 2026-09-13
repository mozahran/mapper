<?php

declare(strict_types=1);

namespace Zahran\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\Cast\CastType;
use Zahran\Mapper\Condition\ConditionType;

/**
 * The cast and condition types stand in for PHP 8.1 enums, so they have to keep the
 * enum guarantees the library leans on: one object per case, and lookup by value.
 */
final class EnumerationTest extends TestCase
{
    public function testACaseIsAlwaysTheSameObject(): void
    {
        $this->assertSame(CastType::Date(), CastType::Date());
        $this->assertSame(CastType::Date(), CastType::from('date'));
        $this->assertSame(ConditionType::Equals(), ConditionType::from('eq'));
    }

    public function testCasesAreLookedUpByTheirTemplateValue(): void
    {
        $this->assertSame('date', CastType::from('date')->value);
        $this->assertSame('not_in', ConditionType::from('not_in')->value);
    }

    public function testTryFromYieldsNullForAnUnknownValue(): void
    {
        $this->assertNull(CastType::tryFrom('cents'));
        $this->assertNull(ConditionType::tryFrom('starts_with'));
    }

    public function testFromRejectsAnUnknownValue(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('"cents" is not a valid value for Zahran\Mapper\Cast\CastType.');

        CastType::from('cents');
    }

    public function testEachTypeKeepsItsOwnCases(): void
    {
        $casts = array_map(static fn (CastType $type): string => $type->value, CastType::cases());
        $conditions = array_map(static fn (ConditionType $type): string => $type->value, ConditionType::cases());

        $this->assertSame(CastType::VALUES, $casts);
        $this->assertSame(ConditionType::VALUES, $conditions);
        $this->assertSame([], array_intersect($casts, $conditions));
    }

    public function testCasesAreNotConstructableFromOutside(): void
    {
        $this->assertFalse((new \ReflectionClass(CastType::class))->isInstantiable());
        $this->assertFalse((new \ReflectionClass(ConditionType::class))->isInstantiable());
    }
}
