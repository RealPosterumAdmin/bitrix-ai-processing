<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Ui;

use Bitrix\Main\Loader;

final class AdminProductButton
{
    public static function onAdminContextMenuShow(array &$items): void
    {
        global $APPLICATION, $USER;

        if (!$USER instanceof \CUser || !$USER->IsAdmin()) {
            return;
        }

        if (!Loader::includeModule('realposterum.ai.processing')) {
            return;
        }

        $page = (string) $APPLICATION->GetCurPage();
        if ($page !== '/bitrix/admin/iblock_element_edit.php') {
            return;
        }

        $productId = (int) ($_REQUEST['ID'] ?? 0);
        $iblockId = (int) ($_REQUEST['IBLOCK_ID'] ?? 0);
        if ($productId <= 0 || $iblockId <= 0) {
            return;
        }

        $items[] = [
            'TEXT' => 'Обработать с ИИ',
            'TITLE' => 'Отправить товар в AI и открыть экран подтверждения',
            'LINK' => '/bitrix/admin/realposterum_ai_processing_tasks.php?action=quick_process&product_id=' . $productId . '&iblock_id=' . $iblockId . '&lang=' . LANGUAGE_ID . '&sessid=' . bitrix_sessid(),
            'ICON' => 'btn_new',
        ];
    }
}
