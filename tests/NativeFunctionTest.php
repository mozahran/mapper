<?php

declare(strict_types=1);

namespace Zahran\Mapper\Tests;

use PHPUnit\Framework\TestCase;
use Zahran\Mapper\Exception\InvalidTemplateException;
use Zahran\Mapper\Mapper;
use Zahran\Mapper\Mutator\NativeFunction;

final class NativeFunctionTest extends TestCase
{
    private Mapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = Mapper::default();
    }

    public function testPassesTheValueAsTheSoleArgumentWhenNoneAreDeclared(): void
    {
        $this->assertSame('ADA', (new NativeFunction('strtoupper'))->apply('ada', []));
    }

    public function testSubstitutesTheValuePlaceholder(): void
    {
        $mutator = new NativeFunction('str_pad');

        $this->assertSame('00042', $mutator->apply('42', [NativeFunction::VALUE_PLACEHOLDER, 5, '0', STR_PAD_LEFT]));
    }

    public function testSubstitutesEveryTopLevelOccurrenceOfThePlaceholder(): void
    {
        $mutator = new NativeFunction('sprintf');

        $this->assertSame(
            'ada-ada',
            $mutator->apply('ada', ['%s-%s', NativeFunction::VALUE_PLACEHOLDER, NativeFunction::VALUE_PLACEHOLDER]),
        );
    }

    public function testDoesNotSubstitutePlaceholdersNestedInsideArguments(): void
    {
        $mutator = new NativeFunction('implode');

        $this->assertSame(
            '__value__',
            $mutator->apply('ada', ['-', [NativeFunction::VALUE_PLACEHOLDER]]),
        );
    }

    public function testOmittingThePlaceholderDropsTheValue(): void
    {
        $this->assertSame('xy', (new NativeFunction('implode'))->apply('ignored', ['', ['x', 'y']]));
    }

    /**
     * @dataProvider provideDeniedFunctions
     */
    public function testDeniesFunctionsOutsideTheAllowList(string $function): void
    {
        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage(sprintf('"%s" is neither a registered mutator nor an allowed PHP function.', $function));

        $this->mapper->compile(sprintf(
            '{"attributes": [{"name": "A", "path": ["a"], "mutators": [{"name": "%s"}]}]}',
            $function,
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDeniedFunctions(): iterable
    {
        foreach ([
            'shell_exec',
            'exec',
            'system',
            'passthru',
            'proc_open',
            'eval',
            'assert',
            'call_user_func',
            'call_user_func_array',
            'array_map',
            'array_filter',
            'usort',
            'preg_replace_callback',
            'file_get_contents',
            'file_put_contents',
            'fopen',
            'unlink',
            'unserialize',
            'extract',
            'create_function',
        ] as $function) {
            yield $function => [$function];
        }
    }

    public function testTheAllowListTakesNoCallableAndInvokesNothing(): void
    {
        $callableTaking = [
            'array_map', 'array_filter', 'array_walk', 'array_reduce', 'usort', 'uasort', 'uksort',
            'call_user_func', 'call_user_func_array', 'preg_replace_callback', 'preg_replace_callback_array',
            'iterator_apply', 'set_error_handler', 'register_shutdown_function', 'spl_autoload_register',
        ];

        $this->assertSame([], array_intersect($callableTaking, NativeFunction::ALLOWED));
    }

    public function testEveryAllowedFunctionExists(): void
    {
        $missing = array_values(array_filter(
            NativeFunction::ALLOWED,
            static fn (string $function): bool => !function_exists($function),
        ));

        $this->assertSame([], $missing);
    }

    public function testTheAllowListHasNoDuplicates(): void
    {
        $this->assertSame(NativeFunction::ALLOWED, array_values(array_unique(NativeFunction::ALLOWED)));
    }

    public function testAllowedFunctionNamesAreMatchedCaseInsensitively(): void
    {
        $template = '{"attributes": [{"name": "Name", "path": ["name"], "mutators": [{"name": "STRTOUPPER"}]}]}';

        $this->assertSame(['Name' => 'ADA'], $this->mapper->map('{"name": "ada"}', $template));
    }
}
