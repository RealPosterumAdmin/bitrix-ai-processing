<?php

namespace RealPosterum\AiProcessing\Model;

class ProductSnapshot
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $properties
     */
    public function __construct(
        private int $productId,
        private int $iblockId,
        private array $fields,
        private array $properties
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'iblock_id' => $this->iblockId,
            'fields' => $this->fields,
            'properties' => $this->properties,
        ];
    }
}
