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

        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$propertyCode => false]);
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
}