<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

interface Step
{
    public function apply(mixed $value): mixed;
}
