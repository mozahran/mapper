<?php

declare(strict_types=1);

namespace Zahran\Mapper\Exception;

final class InvalidJsonException extends \InvalidArgumentException implements MappingException
{
    public static function undecodable(string $subject, \JsonException $previous): self
    {
        return new self(
            sprintf('The %s JSON could not be decoded: %s', $subject, $previous->getMessage()),
            previous: $previous,
        );
    }

    public static function notAStructure(string $subject, string $type): self
    {
        return new self(sprintf('The %s JSON must decode to an object or an array, got %s.', $subject, $type));
    }
}
