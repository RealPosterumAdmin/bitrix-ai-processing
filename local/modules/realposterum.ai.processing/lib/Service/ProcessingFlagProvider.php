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
        $iblockId = $this->settings->getCatalogIblockId();
        $propertyCode = $this->settings->getNeedProcessingPropertyCode();
        if ($iblockId <= 0 || $propertyCode === '') {
            return [];
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $rows = [];
        $result = \CIBlockElement::GetList(
            ['ID' => 'DESC'],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                'PROPERTY_' . $propertyCode => ['Y', '1', 'Да'],
            ],
            false,
            ['nTopCount' => $limit],
            ['ID', 'IBLOCK_ID', 'NAME', 'TIMESTAMP_X']
        );

        while ($row = $result->GetNext(false, false)) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function clearFlag(int $productId): void
    {
        $productId = max(0, $productId);
        if ($productId <= 0 || $this->settings->getFlagSource() !== 'property') {
            return;
        }

        $iblockId = $this->settings->getCatalogIblockId();
        $propertyCode = $this->settings->getNeedProcessingPropertyCode();
        if ($iblockId <= 0 || $propertyCode === '') {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $property = $this->getPropertyMetadata($iblockId, $propertyCode);
        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$propertyCode => $this->buildEmptyValue($property)]);
    }

    /**
     * @param array<int, int|string> $productIds
     */
    public function clearFlags(array $productIds): int
    {
        $cleared = 0;
        foreach ($productIds as $productId) {
            $normalizedId = (int) $productId;
            if ($normalizedId <= 0) {
                continue;
            }

            $this->clearFlag($normalizedId);
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

        return '';
    }
}