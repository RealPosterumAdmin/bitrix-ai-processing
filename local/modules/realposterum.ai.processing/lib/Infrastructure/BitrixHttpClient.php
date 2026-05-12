<?php

namespace RealPosterum\AiProcessing\Infrastructure;

use Bitrix\Main\Web\HttpClient;
use RealPosterum\AiProcessing\Contract\HttpClientInterface;
use RuntimeException;

class BitrixHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $headers, array $payload): string
    {
        $client = new HttpClient([
            'socketTimeout' => 30,
            'streamTimeout' => 30,
            'disableSslVerification' => false,
        ]);

        $client->setHeader('Content-Type', 'application/json', true);
        foreach ($headers as $name => $value) {
            $client->setHeader($name, $value, true);
        }

        $response = $client->post($url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($response === false) {
            throw new RuntimeException('Не удалось выполнить HTTP-запрос к AI API.');
        }

        $status = $client->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('AI API вернул статус ' . $status . '.');
        }

        return (string) $response;
    }
}
