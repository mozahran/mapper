<?php

declare(strict_types=1);

namespace Zahran\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\Cast\Cast;
use Zahran\Mapper\Condition\Predicate;
use Zahran\Mapper\Exception\InvalidTemplateException;
use Zahran\Mapper\Mapper;
use Zahran\Mapper\Mutator\Mutator;

final class ExtensibilityTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testRegistersACustomCondition(): void
    {
        $mapper = $this->mapper->withCondition('starts_with', new class implements Predicate {
            public function matches(mixed $value, mixed $compare): bool
            {
                return is_string($value) && is_string($compare) && str_starts_with($value, $compare);
            }
        });

        $template = '{
            "attributes": [
                {
                    "name": "Kind",
                    "path": ["sku"],
                    "conditions": [{"condition_type": "starts_with", "value": "AB-", "then": "internal", "otherwise": "external"}]
                }
            ]
        }';

        $this->assertSame(['Kind' => 'internal'], $mapper->map('{"sku": "AB-1"}', $template));
        $this->assertSame(['Kind' => 'external'], $mapper->map('{"sku": "ZZ-1"}', $template));
    }

    public function testRegistersACustomCast(): void
    {
        $mapper = $this->mapper->withCast('cents', new class implements Cast {
            public function cast(mixed $value, ?string $format): mixed
            {
                return (int) round(((float) $value) * 100);
            }
        });

        $template = '{"attributes": [{"name": "Amount", "path": ["price"], "cast": {"type": "cents"}}]}';

        $this->assertSame(['Amount' => 4050], $mapper->map('{"price": "40.50"}', $template));
    }

    public function testACustomCastReceivesTheDeclaredFormat(): void
    {
        $mapper = $this->mapper->withCast('padded', new class implements Cast {
            public function cast(mixed $value, ?string $format): mixed
            {
                return str_pad((string) $value, 5, $format ?? ' ', STR_PAD_LEFT);
            }
        });

        $template = '{"attributes": [{"name": "Ref", "path": ["ref"], "cast": {"type": "padded", "format": "0"}}]}';

        $this->assertSame(['Ref' => '00042'], $mapper->map('{"ref": "42"}', $template));
    }

    public function testRegistersACustomMutator(): void
    {
        $mapper = $this->mapper->withMutator('suffix', new class implements Mutator {
            public function apply(mixed $value, array $arguments): mixed
            {
                return $value . ($arguments[0] ?? '');
            }
        });

        $template = '{
            "attributes": [{"name": "Sku", "path": ["sku"], "mutators": [{"name": "suffix", "arguments": ["-EU"]}]}]
        }';

        $this->assertSame(['Sku' => 'AB1-EU'], $mapper->map('{"sku": "AB1"}', $template));
    }

    public function testRegisteredMutatorsWinOverAllowedFunctions(): void
    {
        $mapper = $this->mapper->withMutator('strtoupper', new class implements Mutator {
            public function apply(mixed $value, array $arguments): mixed
            {
                return 'overridden';
            }
        });

        $template = '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "strtoupper"}]}]}';

        $this->assertSame(['Name' => 'overridden'], $mapper->map('{"name": "ada"}', $template));
    }

    public function testOptsIntoAdditionalNativeFunctions(): void
    {
        $template = '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "addslashes"}]}]}';

        $this->assertSame(
            ['Name' => "O\\'Hara"],
            $this->mapper->withFunctions('addslashes')->map('{"name": "O\'Hara"}', $template),
        );
    }

    public function testOptedInFunctionNamesAreCaseInsensitive(): void
    {
        $template = '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "addslashes"}]}]}';

        $this->assertSame(
            ['Name' => "O\\'Hara"],
            $this->mapper->withFunctions('ADDSLASHES')->map('{"name": "O\'Hara"}', $template),
        );
    }

    public function testRejectsFunctionsThatWereNotOptedInto(): void
    {
        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage('"addslashes" is neither a registered mutator nor an allowed PHP function.');

        $this->mapper->compile(
            '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "addslashes"}]}]}',
        );
    }

    public function testExtensionsDoNotLeakIntoTheOriginalMapper(): void
    {
        $extended = $this->mapper->withMutator('suffix', new class implements Mutator {
            public function apply(mixed $value, array $arguments): mixed
            {
                return $value . '!';
            }
        });

        $template = '{"attributes": [{"name": "Sku", "path": ["sku"], "mutators": [{"name": "suffix"}]}]}';

        $this->assertSame(['Sku' => 'AB1!'], $extended->map('{"sku": "AB1"}', $template));

        $this->expectException(InvalidTemplateException::class);
        $this->mapper->compile($template);
    }
}
