<?php

declare(strict_types=1);

namespace Zahran\Mapper\V2\Node;

use Zahran\Mapper\V2\Exception\InvalidPayloadException;
use Zahran\Mapper\V2\Missing;
use Zahran\Mapper\V2\Path;
use Zahran\Mapper\V2\Selection;

final class ListNode implements Node
{
    /**
     * @param Selection|null $selection which elements to map, in what order — null maps
     *                                  every element in the order the payload gave them
     * @param string $attribute the dotted name of this attribute in the output
     */
    public function __construct(
        public string $name,
        private Path $path,
        private ObjectNode $item,
        private ?Selection $selection = null,
        private bool $required = false,
        private bool $strict = false,
        private string $attribute = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function evaluate(mixed $scope): array
    {
        $source = $this->path->read($scope);

        if ($source === Missing::value()) {
            if ($this->required) {
                throw InvalidPayloadException::missing($this->attribute);
            }

            return [];
        }

        if (!is_array($source)) {
            if ($this->required || $this->strict) {
                throw InvalidPayloadException::notAList($this->attribute, get_debug_type($source));
            }

            return [];
        }

        $elements = array_values($source);
        if ($this->selection !== null) {
            $elements = $this->selection->apply($elements);
        }

        $mapped = [];
        foreach ($elements as $element) {
            $mapped[] = $this->item->evaluate($element);
        }

        return $mapped;
    }
}
