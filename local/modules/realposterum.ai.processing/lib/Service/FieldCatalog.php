<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

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

        $properties = [];
        $propertyResult = \CIBlockProperty::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']);
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
}
