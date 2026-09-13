<?php

declare(strict_types=1);

namespace Zahran\Mapper;

final class Literal
{
    public function __construct(
        public mixed $value,
    ) {
    }
}
