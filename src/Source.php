<?php

declare(strict_types=1);

namespace Zahran\Mapper;

/**
 * Whatever an attribute reads its value from: a single path, or several gathered into one.
 */
interface Source
{
    /**
     * Returns Missing::value() when the payload holds nothing at all for this source.
     */
    public function read(mixed $scope): mixed;
}
