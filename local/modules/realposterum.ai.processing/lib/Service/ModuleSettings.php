<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Config\Option;
use RuntimeException;

final class ModuleSettings
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(private string $moduleId, private array $overrides = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return [
            'catalog_iblock_id' => '0',
            'flag_source' => 'property',
            'need_processing_property_code' => 'NeedAiProcessing',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'http_method' => 'POST',
            'timeout' => '30',
            'auth_type' => 'bearer',
            'auth_token' => '',
            'auth_login' => '',
            'auth_password' => '',
            'headers_json' => "{\n    \"Content-Type\": \"application/json\"\n}",
            'model' => 'gpt-4.1-mini',
            'system_prompt' => 'Ты помогаешь улучшать карточки товаров каталога Bitrix. Верни только JSON без пояснений.',
            'request_template' => "{\n    \"model\": #MODEL#,\n    \"response_format\": {\"type\": \"json_object\"},\n    \"messages\": [\n        {\"role\": \"system\", \"content\": #SYSTEM_PROMPT#},\n        {\"role\": \"user\", \"content\": #PAYLOAD_JSON#}\n    ]\n}",
            'response_content_path' => 'choices[0].message.content',
            'outbound_mappings' => [
                ['source_type' => 'field', 'source_code' => 'NAME', 'json_path' => 'product.name'],
                ['source_type' => 'computed', 'source_code' => 'SECTION_PATH', 'json_path' => 'product.category_path'],
                ['source_type' => 'field', 'source_code' => 'PREVIEW_TEXT', 'json_path' => 'product.preview_text'],
                ['source_type' => 'field', 'source_code' => 'DETAIL_TEXT', 'json_path' => 'product.detail_text'],
            ],
            'inbound_mappings' => [
                ['target_type' => 'field', 'target_code' => 'NAME', 'json_path' => 'fields.name', 'allow_empty' => 'N'],
                ['target_type' => 'field', 'target_code' => 'PREVIEW_TEXT', 'json_path' => 'fields.preview_text', 'allow_empty' => 'N'],
                ['target_type' => 'field', 'target_code' => 'DETAIL_TEXT', 'json_path' => 'fields.detail_text', 'allow_empty' => 'N'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $defaults = self::getDefaults();
        $data = [];
        foreach ($defaults as $key => $defaultValue) {
            if (array_key_exists($key, $this->overrides)) {
                $data[$key] = $this->overrides[$key];
                continue;
            }

            if (is_array($defaultValue)) {
                $stored = Option::get($this->moduleId, $key, json_encode($defaultValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $decoded = json_decode((string) $stored, true);
                $data[$key] = is_array($decoded) ? $decoded : $defaultValue;
                continue;
            }

            $data[$key] = Option::get($this->moduleId, $key, (string) $defaultValue);
        }

        return $data;
    }

    public function getCatalogIblockId(): int
    {
        return (int) $this->toArray()['catalog_iblock_id'];
    }

    public function getFlagSource(): string
    {
        return (string) $this->toArray()['flag_source'];
    }

    public function getNeedProcessingPropertyCode(): string
    {
        return trim((string) $this->toArray()['need_processing_property_code']);
    }

    public function getEndpoint(): string
    {
        return trim((string) $this->toArray()['endpoint']);
    }

    public function getHttpMethod(): string
    {
        return strtoupper((string) $this->toArray()['http_method']);
    }

    public function getTimeout(): int
    {
        return max(1, (int) $this->toArray()['timeout']);
    }

    public function getAuthType(): string
    {
        return (string) $this->toArray()['auth_type'];
    }

    public function getAuthToken(): string
    {
        return (string) $this->toArray()['auth_token'];
    }

    public function getAuthLogin(): string
    {
        return (string) $this->toArray()['auth_login'];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->toArray()['auth_password'];
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $decoded = json_decode((string) $this->toArray()['headers_json'], true);
        if (!is_array($decoded)) {
            return ['Content-Type' => 'application/json'];
        }

        $headers = [];
        foreach ($decoded as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $headers[(string) $name] = (string) $value;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    public function getRequestHeaders(): array
    {
        $headers = $this->getHeaders();
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';

        return match ($this->getAuthType()) {
            'bearer' => array_merge($headers, ['Authorization' => 'Bearer ' . trim($this->getAuthToken())]),
            'basic' => array_merge($headers, ['Authorization' => 'Basic ' . base64_encode($this->getAuthLogin() . ':' . $this->getAuthPassword())]),
            default => $headers,
        };
    }

    /**
     * @return array<string, string>
     */
    public function getMaskedRequestHeaders(): array
    {
        $headers = $this->getRequestHeaders();
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = preg_replace('/(.{0,12}).+$/', '$1***', $headers['Authorization']) ?: '***';
        }

        return $headers;
    }

    public function getModel(): string
    {
        return (string) $this->toArray()['model'];
    }

    public function getSystemPrompt(): string
    {
        return (string) $this->toArray()['system_prompt'];
    }

    public function getRequestTemplate(): string
    {
        return (string) $this->toArray()['request_template'];
    }

    public function getResponseContentPath(): string
    {
        return (string) $this->toArray()['response_content_path'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutboundMappings(): array
    {
        return $this->normalizeMappings((array) $this->toArray()['outbound_mappings']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInboundMappings(): array
    {
        return $this->normalizeMappings((array) $this->toArray()['inbound_mappings']);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    public function normalizeAndValidate(array $input, FieldCatalog $catalog, JsonPathResolver $resolver): array
    {
        $defaults = self::getDefaults();
        $data = $defaults;
        foreach ($defaults as $key => $default) {
            if (in_array($key, ['outbound_mappings', 'inbound_mappings'], true)) {
                $data[$key] = $this->normalizeMappings((array) ($input[$key] ?? []));
            } else {
                $data[$key] = is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : $default;
            }
        }

        $errors = [];
        if ((int) $data['catalog_iblock_id'] <= 0) {
            $errors[] = 'Укажите ID каталожного инфоблока.';
        }

        if ($data['flag_source'] === 'property' && trim((string) $data['need_processing_property_code']) === '') {
            $errors[] = 'Для флага из свойства укажите код свойства NeedAiProcessing.';
        }

        if (!filter_var((string) $data['endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Endpoint должен быть корректным URL.';
        }

        if (!in_array($data['http_method'], ['GET', 'POST', 'PUT'], true)) {
            $errors[] = 'HTTP метод должен быть GET, POST или PUT.';
        }

        if ((int) $data['timeout'] <= 0) {
            $errors[] = 'Timeout должен быть больше 0.';
        }

        if (!in_array($data['auth_type'], ['none', 'bearer', 'basic'], true)) {
            $errors[] = 'Некорректный тип авторизации.';
        }

        if ($data['auth_type'] === 'bearer' && trim((string) $data['auth_token']) === '') {
            $errors[] = 'Для bearer авторизации нужен токен.';
        }

        if ($data['auth_type'] === 'basic' && (trim((string) $data['auth_login']) === '' || trim((string) $data['auth_password']) === '')) {
            $errors[] = 'Для basic авторизации нужны логин и пароль.';
        }

        $headers = json_decode((string) $data['headers_json'], true);
        if (!is_array($headers)) {
            $errors[] = 'Headers JSON должен быть JSON-объектом.';
        }

        $requestCheck = str_replace(
            ['#PAYLOAD_JSON#', '#SYSTEM_PROMPT#', '#MODEL#'],
            ['{}', '"test"', '"model"'],
            (string) $data['request_template']
        );
        if (!is_array(json_decode($requestCheck, true))) {
            $errors[] = 'Request template должен формировать корректный JSON.';
        }

        try {
            $resolver->validate((string) $data['response_content_path']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        $iblockId = (int) $data['catalog_iblock_id'];
        foreach ((array) $data['outbound_mappings'] as $row) {
            if (($row['source_type'] ?? '') === '' || ($row['source_code'] ?? '') === '' || ($row['json_path'] ?? '') === '') {
                continue;
            }
            try {
                $catalog->assertSourceExists($iblockId, (string) $row['source_type'], (string) $row['source_code']);
                $resolver->validate((string) $row['json_path']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        foreach ((array) $data['inbound_mappings'] as $row) {
            if (($row['target_type'] ?? '') === '' || ($row['target_code'] ?? '') === '' || ($row['json_path'] ?? '') === '') {
                continue;
            }
            try {
                $catalog->assertSourceExists($iblockId, (string) $row['target_type'], (string) $row['target_code']);
                $resolver->validate((string) $row['json_path']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [$data, $errors];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): void
    {
        foreach (self::getDefaults() as $key => $defaultValue) {
            $value = $data[$key] ?? $defaultValue;
            if (is_array($defaultValue)) {
                Option::set($this->moduleId, $key, (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                continue;
            }
            Option::set($this->moduleId, $key, (string) $value);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMappings(array $mappings): array
    {
        $normalized = [];
        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }
            $row = [];
            foreach ($mapping as $key => $value) {
                $row[(string) $key] = is_scalar($value) ? trim((string) $value) : $value;
            }
            if ($row !== []) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }
}
