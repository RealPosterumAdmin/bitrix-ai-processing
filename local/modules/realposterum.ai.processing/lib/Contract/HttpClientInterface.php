<?php

namespace RealPosterum\AiProcessing\Contract;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    public function postJson(string $url, array $headers, array $payload): string;
}
