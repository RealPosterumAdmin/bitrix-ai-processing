<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Contract;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout): array;
}
