<?php

namespace RealPosterum\AiProcessing\Api;

use RealPosterum\AiProcessing\Contract\HttpClientInterface;
use RealPosterum\AiProcessing\Dto\ProcessingResult;
use RealPosterum\AiProcessing\Service\ModuleSettings;
use RuntimeException;

class AiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ModuleSettings $settings
    ) {
    }

    /**
     * @param array<string, mixed> $productPayload
     */
    public function processProduct(array $productPayload, string $prompt): ProcessingResult
    {
        $token = trim($this->settings->getApiToken());
        if ($token === '') {
            throw new RuntimeException('Не заполнен токен AI API.');
        }

        $payload = [
            'model' => $this->settings->getModel(),
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->settings->getSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'metadata' => [
                'module' => 'realposterum.ai.processing',
                'product_id' => (string) ($productPayload['product_id'] ?? ''),
            ],
        ];

        $response = $this->httpClient->postJson(
            $this->settings->getApiBaseUrl(),
            ['Authorization' => 'Bearer ' . $token],
            $payload
        );

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI API вернул некорректный JSON.');
        }

        $content = $decoded['choices'][0]['message']['content'] ?? $decoded;
        if (is_string($content)) {
            $content = json_decode($content, true);
        }

        if (!is_array($content)) {
            throw new RuntimeException('AI API не вернул ожидаемую JSON-структуру.');
        }

        return new ProcessingResult(
            is_array($content['fields'] ?? null) ? $content['fields'] : [],
            is_array($content['properties'] ?? null) ? $content['properties'] : [],
            (string) ($content['summary'] ?? ''),
            $decoded
        );
    }
}
