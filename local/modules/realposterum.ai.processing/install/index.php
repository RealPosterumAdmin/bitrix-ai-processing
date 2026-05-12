<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;
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
    public $MODULE_DESCRIPTION = 'Очередь AI-обработки товаров с ручным подтверждением в админке Bitrix.';
    public $PARTNER_NAME = 'RealPosterum';
    public $PARTNER_URI = 'https://github.com/RealPosterumAdmin';

    public function __construct()
    {
        $version = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $version['VERSION'] ?? $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $version['VERSION_DATE'] ?? $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installFiles();
        $this->installDB();
    }

    public function DoUninstall(): void
    {
        $this->unInstallDB();
        $this->unInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    protected function installFiles(): void
    {
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
    }

    protected function unInstallFiles(): void
    {
        $adminFiles = [
            '/bitrix/admin/realposterum_ai_processing_tasks.php',
            '/bitrix/admin/realposterum_ai_processing_settings.php',
        ];

        foreach ($adminFiles as $adminFile) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $adminFile;

            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    protected function installDB(): void
    {
        global $DB;

        $sqlBatch = file_get_contents(__DIR__ . '/db/mysql/install.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $sqlBatch))) as $query) {
            $DB->Query($query);
        }
    }

    protected function unInstallDB(): void
    {
        global $DB;

        $sqlBatch = file_get_contents(__DIR__ . '/db/mysql/uninstall.sql');
        foreach (array_filter(array_map('trim', explode(';', (string) $sqlBatch))) as $query) {
            $DB->Query($query);
        }
    }
}
