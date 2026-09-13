<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Cast;

interface Cast
{
    public function cast(mixed $value, ?string $format): mixed;
}
