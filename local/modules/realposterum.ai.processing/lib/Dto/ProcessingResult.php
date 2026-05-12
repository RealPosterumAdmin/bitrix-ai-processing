<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Dto;

final class ProcessingResult
{
    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed> $responseData
     * @param array<int, array<string, mixed>> $comparison
     */
    public function __construct(
        private array $requestPayload,
        private string $requestBody,
        private string $responseBody,
        private array $responseData,
        private array $comparison,
        private string $summary
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_payload' => $this->requestPayload,
            'request_body' => $this->requestBody,
            'response_body' => $this->responseBody,
            'response_data' => $this->responseData,
            'comparison' => $this->comparison,
            'summary' => $this->summary,
        ];
    }
}
