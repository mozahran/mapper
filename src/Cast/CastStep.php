<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Cast;

use Zahran\Mapper\V2\Step;

final class CastStep implements Step
{
    public function __construct(
        private Cast $cast,
        private ?string $format = null,
    ) {
    }

    public function apply(mixed $value): mixed
    {
        return $this->cast->cast($value, $this->format);
    }
}
