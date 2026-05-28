<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Loader;
use RuntimeException;

final class FieldCatalog
{
    /**
     * @return array<string, string>
     */
    public function getElementFields(): array
    {
        return [
            'NAME' => 'Название',
            'CODE' => 'Символьный код',
            'XML_ID' => 'XML_ID',
            'PREVIEW_TEXT' => 'Короткое описание',
            'PREVIEW_TEXT_TYPE' => 'Тип короткого описания',
            'DETAIL_TEXT' => 'Подробное описание',
            'DETAIL_TEXT_TYPE' => 'Тип подробного описания',
            'ACTIVE' => 'Активность',
            'TAGS' => 'Теги',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getComputedFields(): array
    {
        return [
            'SECTION_PATH' => 'Путь разделов',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getPropertyFields(int $iblockId): array
    {
        if ($iblockId <= 0) {
            return [];
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $properties = [];
        $propertyResult = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']
        );

        while ($property = $propertyResult->Fetch()) {
            $code = (string) ($property['CODE'] ?: $property['ID']);
            $properties[$code] = sprintf('%s [%s]', (string) $property['NAME'], $code);
        }

        return $properties;
    }

    public function assertSourceExists(int $iblockId, string $type, string $code): void
    {
        $catalog = match ($type) {
            'field' => $this->getElementFields(),
            'property' => $this->getPropertyFields($iblockId),
            'computed' => $this->getComputedFields(),
            default => throw new RuntimeException('Неизвестный тип поля: ' . $type),
        };

        if (!array_key_exists($code, $catalog)) {
            throw new RuntimeException(sprintf('Поле %s:%s не найдено.', $type, $code));
        }
    }

    /**
     * @return array{type: string}
     */
    public function getInputControl(int $iblockId, string $type, string $code): array
    {
        if ($type === 'field') {
            return match ($code) {
                'NAME', 'CODE', 'XML_ID', 'ACTIVE', 'TAGS', 'PREVIEW_TEXT_TYPE', 'DETAIL_TEXT_TYPE' => ['type' => 'text'],
                'PREVIEW_TEXT', 'DETAIL_TEXT' => ['type' => 'html'],
                default => ['type' => 'textarea'],
            };
        }

        if ($type !== 'property') {
            return ['type' => 'textarea'];
        }

        $property = $this->getPropertyMetadata($iblockId, $code);
        if ($property === null) {
            return ['type' => 'textarea'];
        }

        if ($this->isHtmlProperty($property)) {
            return ['type' => 'html'];
        }

        if ((string) ($property['MULTIPLE'] ?? 'N') === 'Y' || $this->isLongTextProperty($property)) {
            return ['type' => 'textarea'];
        }

        return ['type' => 'text'];
    }

    /**
     * @return array{fields: array<string, mixed>, properties: array<string, mixed>}
     */
    public function prepareValueForSave(int $iblockId, string $type, string $code, mixed $value): array
    {
        if ($type === 'field') {
            if ($code === 'PREVIEW_TEXT' || $code === 'DETAIL_TEXT') {
                return [
                    'fields' => [
                        $code => (string) $value,
                        $code . '_TYPE' => 'html',
                    ],
                    'properties' => [],
                ];
            }

            return [
                'fields' => [$code => $value],
                'properties' => [],
            ];
        }

        if ($type !== 'property') {
            return ['fields' => [], 'properties' => []];
        }

        $property = $this->getPropertyMetadata($iblockId, $code);
        if ($property !== null && $this->isHtmlProperty($property)) {
            return [
                'fields' => [],
                'properties' => [
                    $code => ['VALUE' => ['TEXT' => (string) $value, 'TYPE' => 'html']],
                ],
            ];
        }

        return [
            'fields' => [],
            'properties' => [$code => $value],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPropertyMetadata(int $iblockId, string $code): ?array
    {
        if ($iblockId <= 0 || $code === '') {
            return null;
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $filter = ['IBLOCK_ID' => $iblockId, 'CODE' => $code];
        if (ctype_digit($code)) {
            $filter = [
                'IBLOCK_ID' => $iblockId,
                [
                    'LOGIC' => 'OR',
                    ['CODE' => $code],
                    ['ID' => (int) $code],
                ],
            ];
        }

        $result = \CIBlockProperty::GetList([], $filter);
        $property = $result->Fetch();

        return is_array($property) ? $property : null;
    }

    /**
     * @param array<string, mixed> $property
     */
    private function isHtmlProperty(array $property): bool
    {
        return (string) ($property['PROPERTY_TYPE'] ?? '') === 'S'
            && strtoupper((string) ($property['USER_TYPE'] ?? '')) === 'HTML';
    }

    /**
     * @param array<string, mixed> $property
     */
    private function isLongTextProperty(array $property): bool
    {
        return (int) ($property['ROW_COUNT'] ?? 0) > 1 || (int) ($property['COL_COUNT'] ?? 0) >= 60;
    }
}