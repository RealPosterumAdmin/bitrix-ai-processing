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
}