<?php

declare(strict_types=1);

namespace Zahran\Mapper\Node;

interface Node
{
    public function name(): string;

    public function evaluate(mixed $scope): mixed;
}
