<?php

namespace RealPosterum\AiProcessing\Dto;

class ProcessingResult
{
    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        private array $fields,
        private array $properties,
        private string $summary,
        private array $rawResponse
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fields' => $this->fields,
            'properties' => $this->properties,
            'summary' => $this->summary,
            'raw' => $this->rawResponse,
        ];
    }
}
