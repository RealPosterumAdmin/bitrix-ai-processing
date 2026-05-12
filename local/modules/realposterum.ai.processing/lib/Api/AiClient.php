<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Api;

use RealPosterum\AiProcessing\Contract\HttpClientInterface;
use RealPosterum\AiProcessing\Service\ModuleSettings;
use RuntimeException;

final class AiClient
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function send(ModuleSettings $settings, string $requestBody): array
    {
        $headers = $settings->getRequestHeaders();
        if ($settings->getAuthType() === 'bearer' && trim($settings->getAuthToken()) === '') {
            throw new RuntimeException('Не заполнен токен AI API.');
        }

        if ($settings->getAuthType() === 'basic' && (trim($settings->getAuthLogin()) === '' || trim($settings->getAuthPassword()) === '')) {
            throw new RuntimeException('Не заполнены данные basic auth.');
        }

        $result = $this->httpClient->request(
            $settings->getHttpMethod(),
            $settings->getEndpoint(),
            $headers,
            $requestBody,
            $settings->getTimeout()
        );

        $status = (int) ($result['status'] ?? 0);
        $body = (string) ($result['body'] ?? '');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('AI API вернул статус ' . $status . '.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI API вернул некорректный JSON.');
        }

        return [
            'method' => $settings->getHttpMethod(),
            'endpoint' => $settings->getEndpoint(),
            'headers' => $settings->getMaskedRequestHeaders(),
            'request_body' => $requestBody,
            'response_body' => $body,
            'decoded' => $decoded,
        ];
    }
}
