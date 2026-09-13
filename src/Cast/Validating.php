<?php

declare(strict_types=1);

namespace Zahran\Mapper\Cast;

/**
 * A cast that can say, before it runs, whether a value is one it can carry over
 * faithfully.
 *
 * Only consulted when the mapper is strict. A cast that does not implement this is
 * trusted with whatever it is handed, so custom casts keep working unchanged and opt
 * in to strictness by implementing this.
 */
interface Validating
{
    public function accepts(mixed $value): bool;
}
