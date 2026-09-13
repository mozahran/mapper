<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2;

final class Pipeline
{
    /**
     * @param non-empty-list<Step> $steps
     * @param bool $elementWise whether each step runs over the items of an array value one
     *                          by one. Values gathered from several paths are piped whole
     *                          instead, so that implode() or array_sum() can combine them.
     */
    public function __construct(
        private array $steps,
        private bool $elementWise = true,
    ) {
    }

    public function apply(mixed $value): mixed
    {
        foreach ($this->steps as $step) {
            if ($this->elementWise && is_array($value)) {
                foreach ($value as &$item) {
                    $item = $step->apply($item);
                }
                unset($item);
                continue;
            }
            $value = $step->apply($value);
        }

        return $value;
    }
}
