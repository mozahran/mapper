<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Node;

interface Node
{
    public function name(): string;

    public function evaluate(mixed $scope): mixed;
}
