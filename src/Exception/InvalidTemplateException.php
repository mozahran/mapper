<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Exception;

final class InvalidTemplateException extends \InvalidArgumentException implements MappingException
{
    public static function at(string $context, string $problem): self
    {
        return new self(sprintf('Invalid mapping template at "%s": %s', $context, $problem));
    }
}
