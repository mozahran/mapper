<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\V2\Cast\Cast;
use Zahran\Mapper\V2\Cast\Validating;
use Zahran\Mapper\V2\Exception\InvalidPayloadException;
use Zahran\Mapper\V2\Exception\MappingException;
use Zahran\Mapper\V2\Mapper;

/**
 * What the mapper checks about the payload rather than about the template: attributes
 * the payload must supply, casts that would have to invent an answer, and whatever a
 * cast or a mutator throws on its way out.
 */
final class PayloadValidationTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testARequiredAttributeRejectsAPayloadWithoutIt(): void
    {
        $template = '{"attributes": [{"name": "Sku", "path": ["sku"], "required": true}]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Sku": is required, but the payload holds no value for it.');

        $this->mapper->map('{}', $template);
    }

    public function testARequiredAttributeAcceptsAnExplicitNull(): void
    {
        $template = '{"attributes": [{"name": "Sku", "path": ["sku"], "required": true}]}';

        $this->assertSame(['Sku' => null], $this->mapper->map('{"sku": null}', $template));
    }

    public function testARequiredAttributeIsSatisfiedByAValue(): void
    {
        $template = '{"attributes": [{"name": "Sku", "path": ["sku"], "required": true}]}';

        $this->assertSame(['Sku' => 'A'], $this->mapper->map('{"sku": "A"}', $template));
    }

    public function testARequiredFailureNamesTheAttributeInFull(): void
    {
        $template = '{"attributes": [{
            "name": "Orders",
            "type": "array",
            "path": ["orders"],
            "attributes": [{
                "name": "Lines",
                "type": "array",
                "path": ["lines"],
                "attributes": [{"name": "Sku", "path": ["sku"], "required": true}]
            }]
        }]}';

        $this->expectExceptionMessage('Cannot map the payload at "Orders.Lines.Sku"');

        $this->mapper->map('{"orders": [{"lines": [{"name": "no sku"}]}]}', $template);
    }

    public function testARequiredGatherRejectsAPayloadWithNoneOfItsPaths(): void
    {
        $template = '{"attributes": [{"name": "Name", "paths": [["first"], ["last"]], "required": true}]}';

        $this->expectException(InvalidPayloadException::class);

        $this->mapper->map('{}', $template);
    }

    public function testARequiredListRejectsAMissingSource(): void
    {
        $template = '{"attributes": [
            {"name": "Items", "type": "array", "path": ["items"], "required": true, "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Items": is required');

        $this->mapper->map('{}', $template);
    }

    public function testARequiredListRejectsASourceThatIsNotAList(): void
    {
        $template = '{"attributes": [
            {"name": "Items", "type": "array", "path": ["items"], "required": true, "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Items": expects a list, but the payload holds string.');

        $this->mapper->map('{"items": "nope"}', $template);
    }

    public function testARequiredListAcceptsAnEmptyOne(): void
    {
        $template = '{"attributes": [
            {"name": "Items", "type": "array", "path": ["items"], "required": true, "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->assertSame(['Items' => []], $this->mapper->map('{"items": []}', $template));
    }

    public function testAbsenceIsStillForgivenWithoutRequired(): void
    {
        $template = '{"attributes": [
            {"name": "Sku", "path": ["sku"]},
            {"name": "Items", "type": "array", "path": ["items"], "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->assertSame(['Sku' => null, 'Items' => []], $this->mapper->map('{}', $template));
    }

    public function testAForgivingMapperStillCoercesWhateverItIsGiven(): void
    {
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}]}';

        $this->assertSame(['Count' => 0], $this->mapper->map('{"count": "abc"}', $template));
    }

    public function testAStrictCastRefusesAValueItWouldHaveToInvent(): void
    {
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Count": "abc" (string) cannot be cast to "integer" without losing its meaning.');

        $this->mapper->strict()->map('{"count": "abc"}', $template);
    }

    /**
     * @dataProvider provideStrictlyCastableValues
     */
    public function testAStrictCastCarriesFaithfulValuesOver(string $type, string $payload, mixed $expected): void
    {
        $template = sprintf('{"attributes": [{"name": "Value", "path": ["value"], "cast": {"type": "%s"}}]}', $type);

        $this->assertSame(['Value' => $expected], $this->mapper->strict()->map($payload, $template));
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function provideStrictlyCastableValues(): iterable
    {
        yield 'an integer' => ['integer', '{"value": 42}', 42];
        yield 'a whole numeric string' => ['integer', '{"value": "42"}', 42];
        yield 'a whole float' => ['integer', '{"value": 42.0}', 42];
        yield 'a float' => ['float', '{"value": "40.5"}', 40.5];
        yield 'a boolean' => ['boolean', '{"value": true}', true];
        yield 'a boolean written as a digit' => ['boolean', '{"value": 1}', true];
        yield 'a scalar as a string' => ['string', '{"value": 42}', '42'];
    }

    /**
     * @dataProvider provideStrictlyUncastableValues
     */
    public function testAStrictCastRefusesValuesItCannotCarryOver(string $type, string $payload): void
    {
        $template = sprintf('{"attributes": [{"name": "Value", "path": ["value"], "cast": {"type": "%s"}}]}', $type);

        $this->expectException(InvalidPayloadException::class);

        $this->mapper->strict()->map($payload, $template);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideStrictlyUncastableValues(): iterable
    {
        yield 'a word as an integer' => ['integer', '{"value": "abc"}'];
        yield 'a fractional string as an integer' => ['integer', '{"value": "40.5"}'];
        yield 'a fractional number as an integer' => ['integer', '{"value": 40.5}'];
        yield 'a word as a float' => ['float', '{"value": "abc"}'];
        // (bool) "false" is true, which is exactly the answer strictness is here to refuse.
        yield 'the word false as a boolean' => ['boolean', '{"value": "false"}'];
        yield 'a word as a boolean' => ['boolean', '{"value": "yes"}'];
        yield 'a nested list as a string' => ['string', '{"value": [[1, 2]]}'];
    }

    public function testAStrictCastRefusesADateWithNothingInIt(): void
    {
        $template = '{"attributes": [{"name": "When", "path": ["when"], "cast": {"type": "date", "format": "Y-m-d"}}]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('cannot be cast to "date"');

        $this->mapper->strict()->map('{"when": "   "}', $template);
    }

    public function testAStrictCastCarriesNullThroughUntouched(): void
    {
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}]}';

        $this->assertSame(['Count' => null], $this->mapper->strict()->map('{"count": null}', $template));
        $this->assertSame(['Count' => 0], $this->mapper->map('{"count": null}', $template));
    }

    public function testAStrictCastStillFallsBackOnTheDefault(): void
    {
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "default": 7, "cast": {"type": "integer"}}]}';

        $this->assertSame(['Count' => 7], $this->mapper->strict()->map('{}', $template));
    }

    public function testAStrictListRefusesASourceThatIsNotAList(): void
    {
        $template = '{"attributes": [
            {"name": "Items", "type": "array", "path": ["items"], "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->assertSame(['Items' => []], $this->mapper->map('{"items": "nope"}', $template));

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Items": expects a list, but the payload holds string.');

        $this->mapper->strict()->map('{"items": "nope"}', $template);
    }

    public function testAStrictListStillForgivesAnAbsentSource(): void
    {
        $template = '{"attributes": [
            {"name": "Items", "type": "array", "path": ["items"], "attributes": [{"name": "Sku", "path": ["sku"]}]}
        ]}';

        $this->assertSame(['Items' => []], $this->mapper->strict()->map('{}', $template));
    }

    public function testACustomCastIsTrustedUnlessItSaysOtherwise(): void
    {
        $mapper = $this->mapper->strict()->withCast('cents', new class implements Cast {
            public function cast(mixed $value, ?string $format): mixed
            {
                return (int) round(((float) $value) * 100);
            }
        });

        $template = '{"attributes": [{"name": "Amount", "path": ["price"], "cast": {"type": "cents"}}]}';

        $this->assertSame(['Amount' => 0], $mapper->map('{"price": "nonsense"}', $template));
    }

    public function testACustomCastCanOptIntoStrictness(): void
    {
        $cast = new class implements Cast, Validating {
            public function cast(mixed $value, ?string $format): mixed
            {
                return (int) round(((float) $value) * 100);
            }

            public function accepts(mixed $value): bool
            {
                return is_numeric($value);
            }
        };

        $mapper = $this->mapper->strict()->withCast('cents', $cast);
        $template = '{"attributes": [{"name": "Amount", "path": ["price"], "cast": {"type": "cents"}}]}';

        $this->assertSame(['Amount' => 4050], $mapper->map('{"price": "40.50"}', $template));

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Amount": "nonsense" (string) cannot be cast to "cents"');

        $mapper->map('{"price": "nonsense"}', $template);
    }

    public function testStrictnessSurvivesRegisteringMore(): void
    {
        $mapper = $this->mapper->strict()->withFunctions('addslashes');
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}]}';

        $this->expectException(InvalidPayloadException::class);

        $mapper->map('{"count": "abc"}', $template);
    }

    public function testStrictnessCanBeTurnedBackOff(): void
    {
        $template = '{"attributes": [{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}]}';

        $this->assertSame(['Count' => 0], $this->mapper->strict()->strict(false)->map('{"count": "abc"}', $template));
    }

    public function testAnUnparseableDateIsAMappingExceptionRatherThanWhateverDateTimeThrows(): void
    {
        $template = '{"attributes": [{"name": "When", "path": ["when"], "cast": {"type": "date", "format": "Y-m-d"}}]}';

        try {
            $this->mapper->map('{"when": "the day before never"}', $template);
            $this->fail('Expected the unparseable date to be rejected.');
        } catch (InvalidPayloadException $failure) {
            $this->assertStringContainsString('Cannot map the payload at "When"', $failure->getMessage());
            $this->assertInstanceOf(\Throwable::class, $failure->getPrevious());
        }
    }

    public function testWhateverAMutatorThrowsIsCarriedOutAsAMappingException(): void
    {
        $template = '{"attributes": [
            {"name": "Share", "path": ["total"], "mutators": [{"name": "intdiv", "arguments": ["__value__", 0]}]}
        ]}';

        try {
            $this->mapper->map('{"total": 10}', $template);
            $this->fail('Expected the division by zero to be rejected.');
        } catch (InvalidPayloadException $failure) {
            $this->assertStringContainsString('Cannot map the payload at "Share"', $failure->getMessage());
            $this->assertInstanceOf(\DivisionByZeroError::class, $failure->getPrevious());
        }
    }

    public function testAStrictCastWeighsEveryElementOfAMultiValuedPath(): void
    {
        $template = '{"attributes": [{"name": "Prices", "path": ["items", "*", "price"], "cast": {"type": "integer"}}]}';
        $mapper = $this->mapper->strict();

        $this->assertSame(['Prices' => [1, 2]], $mapper->map('{"items": [{"price": "1"}, {"price": "2"}]}', $template));

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "Prices": "x" (string) cannot be cast to "integer"');

        $mapper->map('{"items": [{"price": "1"}, {"price": "x"}]}', $template);
    }

    public function testARequiredFailureInARootListNamesTheAttribute(): void
    {
        $template = '{"type": "array", "attributes": [{"name": "Sku", "path": ["sku"], "required": true}]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "root.Sku"');

        $this->mapper->map('[{"sku": "A"}, {"note": "none"}]', $template);
    }

    public function testARequiredRootListRejectsAMissingSource(): void
    {
        $template = '{"type": "array", "path": ["items"], "required": true, "attributes": [{"name": "Sku", "path": ["sku"]}]}';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Cannot map the payload at "root": is required');

        $this->mapper->map('{}', $template);
    }

    public function testEveryPayloadErrorIsCatchableAsAMappingException(): void
    {
        $this->expectException(MappingException::class);

        $this->mapper->map('{}', '{"attributes": [{"name": "Sku", "path": ["sku"], "required": true}]}');
    }
}
