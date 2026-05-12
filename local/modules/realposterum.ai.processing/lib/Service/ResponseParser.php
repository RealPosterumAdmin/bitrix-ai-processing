<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Model\ProductSnapshot;
use RuntimeException;

final class ResponseParser
{
    public function __construct(private JsonPathResolver $pathResolver)
    {
    }

    /**
     * @param array<string, mixed> $providerResponse
     * @return array<string, mixed>
     */
    public function parse(array $providerResponse, ProductSnapshot $snapshot, ModuleSettings $settings): array
    {
        $contentPath = $settings->getResponseContentPath();
        $rawContent = $this->pathResolver->get($providerResponse, $contentPath);
        if (is_string($rawContent)) {
            $rawContent = json_decode($rawContent, true);
        }

        if (!is_array($rawContent)) {
            throw new RuntimeException('Не удалось извлечь JSON из ответа AI.');
        }

        $comparison = [];
        $selectedByDefault = [];
        $fields = [];
        $properties = [];

        foreach ($settings->getInboundMappings() as $mapping) {
            $targetType = (string) ($mapping['target_type'] ?? '');
            $targetCode = trim((string) ($mapping['target_code'] ?? ''));
            $jsonPath = trim((string) ($mapping['json_path'] ?? ''));
            $allowEmpty = (string) ($mapping['allow_empty'] ?? 'N') === 'Y';
            if ($targetType === '' || $targetCode === '' || $jsonPath === '') {
                continue;
            }
            if (!$this->pathResolver->exists($rawContent, $jsonPath)) {
                continue;
            }

            $newValue = $this->pathResolver->get($rawContent, $jsonPath);
            if (!$allowEmpty && $this->isEmptyValue($newValue)) {
                continue;
            }

            $oldValue = $snapshot->getTargetValue($targetType, $targetCode);
            $key = $targetType . ':' . $targetCode;
            $changed = $this->normalizeForCompare($oldValue) !== $this->normalizeForCompare($newValue);
            if (!$changed && !$allowEmpty) {
                continue;
            }

            $comparison[] = [
                'key' => $key,
                'target_type' => $targetType,
                'target_code' => $targetCode,
                'label' => $targetCode,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'allow_empty' => $allowEmpty ? 'Y' : 'N',
                'changed' => $changed ? 'Y' : 'N',
            ];
            $selectedByDefault[] = $key;

            if ($targetType === 'field') {
                $fields[$targetCode] = $newValue;
            } else {
                $properties[$targetCode] = $newValue;
            }
        }

        $summary = '';
        if ($this->pathResolver->exists($rawContent, 'summary')) {
            $summary = (string) $this->pathResolver->get($rawContent, 'summary');
        }

        return [
            'summary' => $summary,
            'content' => $rawContent,
            'comparison' => $comparison,
            'fields' => $fields,
            'properties' => $properties,
            'selected_by_default' => $selectedByDefault,
        ];
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function normalizeForCompare(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
