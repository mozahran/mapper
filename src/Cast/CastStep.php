<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Cast;

use Zahran\Mapper\V2\Exception\InvalidPayloadException;
use Zahran\Mapper\V2\Step;

final class CastStep implements Step
{
    public function __construct(
        private Cast $cast,
        private string $type,
        private ?string $format = null,
        private bool $strict = false,
    ) {
    }

    public function apply(mixed $value): mixed
    {
        if (!$this->strict) {
            return $this->cast->cast($value, $this->format);
        }

        // Strictly, null is the absence of a value, not a value to be turned into 0,
        // "" or false; it is carried through untouched instead.
        if ($value === null) {
            return null;
        }

        if ($this->cast instanceof Validating && !$this->cast->accepts($value)) {
            throw InvalidPayloadException::notCastable($value, $this->type);
        }

        return $this->cast->cast($value, $this->format);
    }
}
