<?php

declare(strict_types=1);

namespace Zahran\Mapper\Exception;

/**
 * A problem with the payload rather than with the template: raised while mapping, where
 * InvalidTemplateException is raised while compiling.
 *
 * Mapping stays forgiving by default — a missing path falls back to its default, a
 * source that is not an array yields an empty list. These are raised only where the
 * template asked for them, by declaring an attribute "required" or by mapping strictly,
 * and to carry out whatever a cast or a mutator threw.
 */
final class InvalidPayloadException extends \RuntimeException implements MappingException
{
    public static function missing(string $attribute): self
    {
        return self::at($attribute, 'is required, but the payload holds no value for it.');
    }

    public static function notAList(string $attribute, string $type): self
    {
        return self::at($attribute, sprintf('expects a list, but the payload holds %s.', $type));
    }

    /**
     * Raised without the attribute it happened in, which only the node knows; wrapped in
     * one that names it by self::inAttribute().
     */
    public static function notCastable(mixed $value, string $cast): self
    {
        return new self(sprintf('%s cannot be cast to "%s" without losing its meaning.', self::describe($value), $cast));
    }

    public static function inAttribute(string $attribute, \Throwable $failure): self
    {
        return self::at($attribute, $failure->getMessage(), $failure);
    }

    private static function at(string $attribute, string $problem, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Cannot map the payload at "%s": %s', $attribute, $problem), 0, $previous);
    }

    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return sprintf('"%s" (string)', $value);
        }

        if (is_scalar($value)) {
            return sprintf('%s (%s)', var_export($value, true), get_debug_type($value));
        }

        return get_debug_type($value);
    }
}
