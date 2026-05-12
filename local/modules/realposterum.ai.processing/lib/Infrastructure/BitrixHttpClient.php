<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Infrastructure;

use Bitrix\Main\Web\HttpClient;
use RealPosterum\AiProcessing\Contract\HttpClientInterface;
use RuntimeException;

final class BitrixHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, ['GET', 'POST', 'PUT'], true)) {
            throw new RuntimeException('Поддерживаются только GET, POST и PUT запросы.');
        }

        $client = new HttpClient([
            'socketTimeout' => $timeout,
            'streamTimeout' => $timeout,
            'disableSslVerification' => false,
        ]);

        foreach ($headers as $name => $value) {
            $client->setHeader($name, $value, true);
        }

        if ($method === 'GET') {
            $response = $client->get($url);
        } elseif ($method === 'PUT') {
            $response = $client->query('PUT', $url, $body ?? '');
        } else {
            $response = $client->post($url, $body ?? '');
        }

        if ($response === false) {
            throw new RuntimeException('Не удалось выполнить HTTP-запрос к AI API.');
        }

        return [
            'status' => (int) $client->getStatus(),
            'body' => (string) $response,
        ];
    }
}
