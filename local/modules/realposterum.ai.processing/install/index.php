<?php

declare(strict_types=1);

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

if (!class_exists('CModule')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
}

class realposterum_ai_processing extends CModule
{
    public const MODULE_ID = 'realposterum.ai.processing';

    public $MODULE_ID = self::MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'RealPosterum AI Processing';
    public $MODULE_DESCRIPTION = 'AI-обработка карточек товаров с промежуточным хранением и ручным подтверждением.';
    public $PARTNER_NAME = 'RealPosterum';
    public $PARTNER_URI = 'https://github.com/RealPosterumAdmin';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '0.1.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '2026-05-12 00:00:00';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installDB();
        $this->installFiles();
        $this->registerEvents();
    }

    public function DoUninstall(): void
    {
        $this->unRegisterEvents();
        $this->unInstallFiles();
        $this->unInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installFiles(): void
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
    }

    public function unInstallFiles(): void
    {
        foreach (['/bitrix/admin/realposterum_ai_processing_tasks.php', '/bitrix/admin/realposterum_ai_processing_settings.php'] as $file) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $file;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    public function installDB(): void
    {
        global $DB;

        $sqlBatch = file_get_contents(__DIR__ . '/db/mysql/install.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $sqlBatch))) as $query) {
            $DB->Query($query);
        }
    }

    public function unInstallDB(): void
    {
        global $DB;

        $sqlBatch = file_get_contents(__DIR__ . '/db/mysql/uninstall.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $sqlBatch))) as $query) {
            $DB->Query($query);
        }
    }

    public function registerEvents(): void
    {
        EventManager::getInstance()->registerEventHandlerCompatible(
            'main',
            'OnAdminContextMenuShow',
            $this->MODULE_ID,
            'RealPosterum\\AiProcessing\\Ui\\AdminProductButton',
            'onAdminContextMenuShow'
        );
    }

    public function unRegisterEvents(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnAdminContextMenuShow',
            $this->MODULE_ID,
            'RealPosterum\\AiProcessing\\Ui\\AdminProductButton',
            'onAdminContextMenuShow'
        );
    }
}
