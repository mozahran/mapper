<?php

declare(strict_types=1);

namespace Zahran\Mapper\Node;

use Zahran\Mapper\Exception\InvalidPayloadException;
use Zahran\Mapper\Missing;
use Zahran\Mapper\Pipeline;
use Zahran\Mapper\Source;

final class ValueNode implements Node
{
    /**
     * @param string $attribute the dotted name of this attribute in the output, which
     *                          is what a payload error points at
     */
    public function __construct(
        public string $name,
        private Source $source,
        private mixed $default = null,
        private ?Pipeline $pipeline = null,
        private bool $required = false,
        private string $attribute = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function evaluate(mixed $scope): mixed
    {
        $value = $this->source->read($scope);

        if ($value === Missing::value()) {
            if ($this->required) {
                throw InvalidPayloadException::missing($this->attribute);
            }

            return $this->default;
        }

        if ($this->pipeline === null) {
            return $value;
        }

        // Whatever a cast or a mutator throws is the payload's problem, not the caller's
        // to catch by hand: it is carried out as a MappingException naming the attribute.
        try {
            return $this->pipeline->apply($value);
        } catch (\Throwable $failure) {
            throw InvalidPayloadException::inAttribute($this->attribute, $failure);
        }
    }
}
