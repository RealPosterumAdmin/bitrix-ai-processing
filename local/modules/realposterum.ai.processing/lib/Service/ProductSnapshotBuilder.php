<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Model\ProductSnapshot;
use RuntimeException;

class ProductSnapshotBuilder
{
    public function build(int $productId, int $iblockId): ProductSnapshot
    {
        if ($productId <= 0 || $iblockId <= 0) {
            throw new RuntimeException('Нужно передать корректные PRODUCT_ID и IBLOCK_ID.');
        }

        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }

        $element = \CIBlockElement::GetList(
            [],
            ['ID' => $productId, 'IBLOCK_ID' => $iblockId],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME', 'ACTIVE', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT']
        )->GetNext();

        if (!is_array($element)) {
            throw new RuntimeException('Товар не найден в указанном инфоблоке.');
        }

        $properties = [];
        $propertyResult = \CIBlockElement::GetProperty($iblockId, $productId, ['sort' => 'asc'], ['ACTIVE' => 'Y']);
        while ($property = $propertyResult->Fetch()) {
            $code = (string) ($property['CODE'] ?: $property['ID']);
            $properties[$code] = $property['VALUE'];
        }

        return new ProductSnapshot($productId, $iblockId, $element, $properties);
    }
}