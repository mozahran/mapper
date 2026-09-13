<?php

declare(strict_types=1);

namespace Zahran\Mapper\Mutator;

/**
 * Calls a PHP function named by the mapping template.
 *
 * Only functions on the allow list may be called: a template is data, and data must
 * never be able to reach arbitrary code. Every entry below is side-effect free and
 * takes no callable argument, so no listed function can be used to invoke another.
 */
final class NativeFunction implements Mutator
{
    public const VALUE_PLACEHOLDER = '__value__';

    /**
     * @var non-empty-list<string>
     */
    public const ALLOWED = [
        'abs',
        'array_flip',
        'array_keys',
        'array_product',
        'array_reverse',
        'array_slice',
        'array_sum',
        'array_unique',
        'array_values',
        'base64_decode',
        'base64_encode',
        'bin2hex',
        'boolval',
        'ceil',
        'count',
        'date',
        'dechex',
        'explode',
        'floatval',
        'floor',
        'gettype',
        'gmdate',
        'hexdec',
        'htmlspecialchars',
        'implode',
        'intdiv',
        'intval',
        'json_decode',
        'json_encode',
        'lcfirst',
        'ltrim',
        'max',
        'mb_strlen',
        'mb_strtolower',
        'mb_strtoupper',
        'mb_substr',
        'md5',
        'min',
        'nl2br',
        'number_format',
        'pow',
        'preg_quote',
        'preg_replace',
        'preg_split',
        'rawurlencode',
        'round',
        'rtrim',
        'sha1',
        'sprintf',
        'sqrt',
        'str_contains',
        'str_ends_with',
        'str_pad',
        'str_repeat',
        'str_replace',
        'str_split',
        'str_starts_with',
        'str_word_count',
        'strip_tags',
        'strlen',
        'strrev',
        'strtolower',
        'strtotime',
        'strtoupper',
        'strtr',
        'strval',
        'substr',
        'substr_count',
        'trim',
        'ucfirst',
        'ucwords',
        'urlencode',
        'vsprintf',
        'wordwrap',
    ];

    public function __construct(
        private string $function,
    ) {
    }

    public function apply(mixed $value, array $arguments): mixed
    {
        if ($arguments === []) {
            return ($this->function)($value);
        }

        foreach ($arguments as $index => $argument) {
            if ($argument === self::VALUE_PLACEHOLDER) {
                $arguments[$index] = $value;
            }
        }

        return ($this->function)(...$arguments);
    }
}
