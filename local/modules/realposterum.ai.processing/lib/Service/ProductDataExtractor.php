<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Model\ProductSnapshot;
use RuntimeException;

final class ProductDataExtractor
{
    public function buildSnapshot(int $productId, int $iblockId): ProductSnapshot
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
            ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'XML_ID', 'ACTIVE', 'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'DETAIL_TEXT', 'DETAIL_TEXT_TYPE', 'TAGS']
        )->GetNext(false, false);

        if (!is_array($element)) {
            throw new RuntimeException('Товар не найден в указанном инфоблоке.');
        }

        $properties = [];
        $propertyResult = \CIBlockElement::GetProperty($iblockId, $productId, ['SORT' => 'ASC', 'ID' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($property = $propertyResult->Fetch()) {
            $code = (string) ($property['CODE'] ?: $property['ID']);
            $value = $property['VALUE'];
            if ((string) $property['MULTIPLE'] === 'Y') {
                $properties[$code] ??= [];
                if ($value !== '' && $value !== null) {
                    $properties[$code][] = $value;
                }
                continue;
            }
            $properties[$code] = $value;
        }

        return new ProductSnapshot(
            $productId,
            $iblockId,
            $element,
            $properties,
            ['SECTION_PATH' => $this->buildSectionPath($productId, $iblockId)]
        );
    }

    private function buildSectionPath(int $productId, int $iblockId): string
    {
        $sections = [];
        $groupResult = \CIBlockElement::GetElementGroups($productId, true, ['ID', 'NAME', 'IBLOCK_SECTION_ID']);
        while ($group = $groupResult->Fetch()) {
            $chain = [];
            $navResult = \CIBlockSection::GetNavChain($iblockId, (int) $group['ID'], ['ID', 'NAME']);
            while ($section = $navResult->Fetch()) {
                $chain[] = (string) $section['NAME'];
            }

            if ($chain !== []) {
                $sections[] = implode(' / ', $chain);
            }
        }

        return implode(' | ', array_unique($sections));
    }
}
