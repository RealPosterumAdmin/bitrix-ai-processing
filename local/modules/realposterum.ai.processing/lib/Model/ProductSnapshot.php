<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Model;

use RuntimeException;

final class ProductSnapshot
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $computed
     */
    public function __construct(
        private int $productId,
        private int $iblockId,
        private array $fields,
        private array $properties,
        private array $computed = []
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['product_id'] ?? 0),
            (int) ($data['iblock_id'] ?? 0),
            is_array($data['fields'] ?? null) ? $data['fields'] : [],
            is_array($data['properties'] ?? null) ? $data['properties'] : [],
            is_array($data['computed'] ?? null) ? $data['computed'] : []
        );
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getIblockId(): int
    {
        return $this->iblockId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function getComputed(): array
    {
        return $this->computed;
    }

    public function getSourceValue(string $type, string $code): mixed
    {
        $code = trim($code);
        return match ($type) {
            'field' => $this->fields[$code] ?? null,
            'property' => $this->properties[$code] ?? null,
            'computed' => $this->computed[$code] ?? null,
            default => throw new RuntimeException('Неизвестный тип источника: ' . $type),
        };
    }

    public function getTargetValue(string $type, string $code): mixed
    {
        return $this->getSourceValue($type, $code);
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
            'computed' => $this->computed,
        ];
    }
}
