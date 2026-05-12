<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Api\AiClient;
use RealPosterum\AiProcessing\Infrastructure\BitrixHttpClient;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RealPosterum\AiProcessing\Service\DecisionService;
use RealPosterum\AiProcessing\Service\ModuleSettings;
use RealPosterum\AiProcessing\Service\ProcessingService;
use RealPosterum\AiProcessing\Service\ProductSnapshotBuilder;
use RealPosterum\AiProcessing\Service\PromptBuilder;

$moduleId = 'realposterum.ai.processing';
$message = null;
$messageType = 'ok';

if (!Loader::includeModule($moduleId)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message">Модуль не установлен.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$repository = new ProcessingTaskRepository();
$settings = new ModuleSettings($moduleId);
$processingService = new ProcessingService(
    $repository,
    new ProductSnapshotBuilder(),
    new PromptBuilder($settings),
    new AiClient(new BitrixHttpClient(), $settings)
);
$decisionService = new DecisionService($repository);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'queue') {
            $taskId = $processingService->queueProduct(
                (int) ($_POST['product_id'] ?? 0),
                (int) ($_POST['iblock_id'] ?? 0)
            );
            $message = 'Задача поставлена в очередь, ID: ' . $taskId;
        } elseif ($action === 'process') {
            $processingService->processTask((int) ($_POST['task_id'] ?? 0));
            $message = 'Задача обработана AI.';
        } elseif ($action === 'approve') {
            $decisionService->approve((int) ($_POST['task_id'] ?? 0));
            $message = 'AI-изменения применены к товару.';
        } elseif ($action === 'reject') {
            $decisionService->reject((int) ($_POST['task_id'] ?? 0));
            $message = 'Задача отклонена.';
        }
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    }
}

$tasks = $repository->findRecent(50);

$APPLICATION->SetTitle('Очередь RealPosterum AI Processing');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($message !== null): ?>
    <div class="adm-info-message-wrap">
        <div class="adm-info-message<?= $messageType === 'error' ? ' adm-info-message-red' : '' ?>">
            <?= htmlspecialcharsbx($message) ?>
        </div>
    </div>
<?php endif; ?>

<div style="margin-bottom: 24px;">
    <form method="post">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="queue">
        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%">ID инфоблока</td>
                <td width="60%"><input type="number" name="iblock_id" min="1" required></td>
            </tr>
            <tr>
                <td>ID товара</td>
                <td><input type="number" name="product_id" min="1" required></td>
            </tr>
            <tr>
                <td></td>
                <td><button class="adm-btn-save" type="submit">Поставить в очередь</button></td>
            </tr>
        </table>
    </form>
</div>

<table class="adm-list-table" style="width: 100%;">
    <thead>
    <tr class="adm-list-table-header">
        <td class="adm-list-table-cell">ID</td>
        <td class="adm-list-table-cell">Товар</td>
        <td class="adm-list-table-cell">Статус</td>
        <td class="adm-list-table-cell">Создано</td>
        <td class="adm-list-table-cell">AI summary</td>
        <td class="adm-list-table-cell">Действия</td>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($tasks as $task): ?>
        <?php
        $result = $task['RESULT_JSON'] ? json_decode((string) $task['RESULT_JSON'], true) : [];
        $summary = is_array($result) ? (string) ($result['summary'] ?? '') : '';
        ?>
        <tr class="adm-list-table-row">
            <td class="adm-list-table-cell"><?= (int) $task['ID'] ?></td>
            <td class="adm-list-table-cell">
                #<?= (int) $task['PRODUCT_ID'] ?> / IBLOCK <?= (int) $task['SOURCE_IBLOCK_ID'] ?>
            </td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $task['STATUS']) ?></td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $task['CREATED_AT']) ?></td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx($summary) ?></td>
            <td class="adm-list-table-cell">
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['ID'] ?>">
                        <input type="hidden" name="action" value="process">
                        <button type="submit" class="adm-btn">Обработать</button>
                    </form>
                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['ID'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="adm-btn-save">Применить</button>
                    </form>
                    <form method="post">
                        <?= bitrix_sessid_post() ?>
                        <input type="hidden" name="task_id" value="<?= (int) $task['ID'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="adm-btn">Отклонить</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
