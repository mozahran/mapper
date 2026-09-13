<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

use Zahran\Mapper\V2\Exception\InvalidJsonException;

/**
 * @internal
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function decode(string $json, string $subject): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidJsonException::undecodable($subject, $exception);
        }

        if (!is_array($decoded)) {
            throw InvalidJsonException::notAStructure($subject, get_debug_type($decoded));
        }

        return $decoded;
    }
}
