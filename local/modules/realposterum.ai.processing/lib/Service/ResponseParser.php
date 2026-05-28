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

            $resolvedValue = $this->resolveValue($rawContent, $targetType, $targetCode, $jsonPath);
            if (!$resolvedValue['found']) {
                continue;
            }

            $newValue = $resolvedValue['value'];
            if (!$allowEmpty && $this->isEmptyValue($newValue)) {
                continue;
            }

            $oldValue = $snapshot->getTargetValue($targetType, $targetCode);
            $key = $targetType . ':' . $targetCode;
            $changed = $this->normalizeForCompare($oldValue) !== $this->normalizeForCompare($newValue);
            if (!$changed) {
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

    /**
     * @param array<string, mixed> $rawContent
     * @return array{found: bool, value: mixed}
     */
    private function resolveValue(array $rawContent, string $targetType, string $targetCode, string $jsonPath): array
    {
        foreach ($this->buildCandidatePaths($targetType, $targetCode, $jsonPath) as $candidatePath) {
            if (!$this->pathResolver->exists($rawContent, $candidatePath)) {
                continue;
            }

            return [
                'found' => true,
                'value' => $this->pathResolver->get($rawContent, $candidatePath),
            ];
        }

        return [
            'found' => false,
            'value' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildCandidatePaths(string $targetType, string $targetCode, string $jsonPath): array
    {
        $candidates = [$jsonPath];

        if (preg_match('/^(.+)\.([A-Za-z0-9_\-]+)$/', $jsonPath, $matches) === 1) {
            $prefix = $matches[1];
            $lastSegment = $matches[2];
            $candidates[] = $prefix . '.' . strtolower($lastSegment);
            $candidates[] = $prefix . '.' . strtoupper($lastSegment);
        } else {
            $candidates[] = strtolower($jsonPath);
            $candidates[] = strtoupper($jsonPath);
        }

        if ($targetType === 'field') {
            $normalizedCode = strtoupper($targetCode);
            $codeLower = strtolower($targetCode);

            $candidates[] = 'fields.' . $normalizedCode;
            $candidates[] = 'fields.' . $codeLower;
            $candidates[] = 'content.' . $codeLower;

            if ($normalizedCode === 'NAME') {
                $candidates[] = 'content.title';
                $candidates[] = 'content.name';
            } elseif ($normalizedCode === 'PREVIEW_TEXT') {
                $candidates[] = 'content.preview_text';
            } elseif ($normalizedCode === 'DETAIL_TEXT') {
                $candidates[] = 'content.detail_text';
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn ($path): bool => $path !== '')));
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
