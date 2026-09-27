<?php

namespace Modules\Product\DTO;

use App\Models\Product;

final readonly class ProductDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $sku,
        public string $status,
    ) {}

    public static function fromModel(Product $product): self
    {
        return new self(
            id: (int) $product->getKey(),
            title: $product->title,
            slug: $product->slug,
            sku: $product->sku,
            status: $product->status,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'status' => $this->status,
        ];
    }
}
