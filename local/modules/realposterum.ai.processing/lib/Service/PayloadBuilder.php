<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Model\ProductSnapshot;

final class PayloadBuilder
{
    public function __construct(private JsonPathResolver $pathResolver)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @return array<string, mixed>
     */
    public function build(ProductSnapshot $snapshot, array $mappings): array
    {
        if ($mappings === []) {
            return [
                'product' => [
                    'name' => $snapshot->getTargetValue('field', 'NAME'),
                    'preview_text' => $snapshot->getTargetValue('field', 'PREVIEW_TEXT'),
                    'detail_text' => $snapshot->getTargetValue('field', 'DETAIL_TEXT'),
                    'section_path' => $snapshot->getTargetValue('computed', 'SECTION_PATH'),
                ],
            ];
        }

        $payload = [];
        foreach ($mappings as $mapping) {
            $sourceType = (string) ($mapping['source_type'] ?? '');
            $sourceCode = trim((string) ($mapping['source_code'] ?? ''));
            $jsonPath = trim((string) ($mapping['json_path'] ?? ''));
            if ($sourceType === '' || $sourceCode === '' || $jsonPath === '') {
                continue;
            }

            $value = $snapshot->getSourceValue($sourceType, $sourceCode);
            $this->pathResolver->set($payload, $jsonPath, $value);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $mappedPayload
     */
    public function renderRequestBody(array $mappedPayload, ModuleSettings $settings): string
    {
        $template = $settings->getRequestTemplate();
        $replaced = str_replace(
            ['#PAYLOAD_JSON#', '#SYSTEM_PROMPT#', '#MODEL#'],
            [
                json_encode($mappedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                json_encode($settings->getSystemPrompt(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($settings->getModel(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            $template
        );

        $decoded = json_decode($replaced, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Шаблон request body должен давать корректный JSON.');
        }

        return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
