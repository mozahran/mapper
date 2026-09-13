<?php

declare(strict_types=1);

namespace Zahran\Mapper;

interface Step
{
    public function apply(mixed $value): mixed;
}
