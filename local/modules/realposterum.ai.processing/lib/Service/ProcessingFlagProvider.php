<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Loader;
use RuntimeException;

final class ProcessingFlagProvider
{
    public function __construct(private ModuleSettings $settings)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMarkedProducts(int $limit = 100): array
    {
        if ($this->settings->getFlagSource() !== 'property') {
            return [];
        }

        $iblockId = $this->settings->getCatalogIblockId();
        $propertyCode = $this->settings->getNeedProcessingPropertyCode();
        if ($iblockId <= 0 || $propertyCode === '') {
            return [];
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $property = $this->getRequiredProperty($iblockId, $propertyCode);
        $propertyId = (int) ($property['ID'] ?? 0);
        $propertyKey = (string) (($property['CODE'] ?? '') ?: $propertyId);
        $rows = [];
        $result = \CIBlockElement::GetList(
            ['ID' => 'DESC'],
            [
                'IBLOCK_ID' => $iblockId,
                '!PROPERTY_' . $propertyId => false,
            ],
            false,
            ['nTopCount' => $limit],
            ['ID', 'IBLOCK_ID', 'NAME', 'TIMESTAMP_X', 'ACTIVE']
        );

        while ($row = $result->GetNext(false, false)) {
            if (!is_array($row)) {
                continue;
            }

            $flagValue = $this->getElementPropertyValue($iblockId, (int) $row['ID'], $propertyId);
            if ($this->isEmptyValue($flagValue)) {
                continue;
            }

            $row['FLAG_PROPERTY_CODE'] = $propertyKey;
            $row['FLAG_PROPERTY_NAME'] = (string) ($property['NAME'] ?? $propertyKey);
            $row['FLAG_VALUE'] = $flagValue;
            $rows[] = $row;
        }

        return $rows;
    }

    public function clearFlag(int $productId, ?int $iblockId = null): void
    {
        $productId = max(0, $productId);
        if ($productId <= 0 || $this->settings->getFlagSource() !== 'property') {
            return;
        }

        $iblockId = $iblockId !== null ? max(0, $iblockId) : $this->settings->getCatalogIblockId();
        $propertyCode = $this->settings->getNeedProcessingPropertyCode();
        if ($iblockId <= 0 || $propertyCode === '') {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $property = $this->getRequiredProperty($iblockId, $propertyCode);
        $propertyId = (int) ($property['ID'] ?? 0);
        $propertyKey = (string) (($property['CODE'] ?? '') ?: $propertyId);
        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$propertyKey => $this->buildEmptyValue($property)]);

        $remainingValue = $this->getElementPropertyValue($iblockId, $productId, $propertyId);
        if (!$this->isEmptyValue($remainingValue)) {
            throw new RuntimeException(sprintf(
                'Не удалось снять флаг %s у товара #%d. Текущее значение: %s',
                $propertyKey,
                $productId,
                $this->stringifyValue($remainingValue)
            ));
        }
    }

    /**
     * @param array<int, int|string> $productIds
     */
    public function clearFlags(array $productIds, ?int $iblockId = null): int
    {
        $cleared = 0;
        foreach ($productIds as $productId) {
            $normalizedId = (int) $productId;
            if ($normalizedId <= 0) {
                continue;
            }

            $this->clearFlag($normalizedId, $iblockId);
            $cleared++;
        }

        return $cleared;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPropertyMetadata(int $iblockId, string $propertyCode): ?array
    {
        $filter = ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode];
        if (ctype_digit($propertyCode)) {
            $filter = [
                'IBLOCK_ID' => $iblockId,
                [
                    'LOGIC' => 'OR',
                    ['CODE' => $propertyCode],
                    ['ID' => (int) $propertyCode],
                ],
            ];
        }

        $result = \CIBlockProperty::GetList([], $filter);
        $property = $result->Fetch();

        return is_array($property) ? $property : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequiredProperty(int $iblockId, string $propertyCode): array
    {
        $property = $this->getPropertyMetadata($iblockId, $propertyCode);
        if ($property === null) {
            throw new RuntimeException(sprintf(
                'Свойство флага %s не найдено в инфоблоке #%d.',
                $propertyCode,
                $iblockId
            ));
        }

        return $property;
    }

    private function getElementPropertyValue(int $iblockId, int $productId, int $propertyId): mixed
    {
        $result = \CIBlockElement::GetProperty(
            $iblockId,
            $productId,
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['ID' => $propertyId]
        );

        $values = [];
        $isMultiple = false;
        while ($property = $result->Fetch()) {
            if (!is_array($property)) {
                continue;
            }

            $isMultiple = (string) ($property['MULTIPLE'] ?? 'N') === 'Y';
            $value = $property['VALUE_ENUM'] ?? $property['VALUE'] ?? null;
            if ($isMultiple) {
                if (!$this->isEmptyValue($value)) {
                    $values[] = $value;
                }
                continue;
            }

            return $value;
        }

        return $isMultiple ? $values : null;
    }

    /**
     * @param array<string, mixed>|null $property
     */
    private function buildEmptyValue(?array $property): mixed
    {
        if ($property === null) {
            return false;
        }

        if ((string) ($property['PROPERTY_TYPE'] ?? '') === 'S' && strtoupper((string) ($property['USER_TYPE'] ?? '')) === 'HTML') {
            return ['VALUE' => ['TEXT' => '', 'TYPE' => 'html']];
        }

        if ((string) ($property['MULTIPLE'] ?? 'N') === 'Y') {
            return [];
        }

        return false;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === false || $value === [];
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}