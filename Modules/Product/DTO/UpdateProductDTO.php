<?php

namespace Modules\Product\DTO;

final readonly class UpdateProductDTO
{
    public function __construct(private array $values) {}

    public static function fromValidated(array $values): self
    {
        return new self($values);
    }

    public function toArray(): array
    {
        return $this->values;
    }
}
