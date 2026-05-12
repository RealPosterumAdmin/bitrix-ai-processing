<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Api\AiClient;
use RealPosterum\AiProcessing\Enum\TaskStatus;
use RealPosterum\AiProcessing\Infrastructure\BitrixHttpClient;
use RealPosterum\AiProcessing\Repository\ProcessingLogRepository;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RealPosterum\AiProcessing\Service\DecisionService;
use RealPosterum\AiProcessing\Service\JsonPathResolver;
use RealPosterum\AiProcessing\Service\LogService;
use RealPosterum\AiProcessing\Service\ModuleSettings;
use RealPosterum\AiProcessing\Service\PayloadBuilder;
use RealPosterum\AiProcessing\Service\ProcessingFlagProvider;
use RealPosterum\AiProcessing\Service\ProcessingService;
use RealPosterum\AiProcessing\Service\ProductDataExtractor;
use RealPosterum\AiProcessing\Service\ResponseParser;

$moduleId = 'realposterum.ai.processing';
$message = null;
$messageType = 'ok';

if (!Loader::includeModule($moduleId)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message">Модуль не установлен.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$settings = new ModuleSettings($moduleId);
$taskRepository = new ProcessingTaskRepository();
$logRepository = new ProcessingLogRepository();
$logService = new LogService($logRepository);
$processingService = new ProcessingService(
    $taskRepository,
    new ProductDataExtractor(),
    new PayloadBuilder(new JsonPathResolver()),
    new ResponseParser(new JsonPathResolver()),
    new AiClient(new BitrixHttpClient()),
    $settings,
    $logService
);
$decisionService = new DecisionService($taskRepository, $settings, $logService);
$flagProvider = new ProcessingFlagProvider($settings);

try {
    if (($_GET['action'] ?? '') === 'quick_process' && check_bitrix_sessid()) {
        $taskId = $processingService->queueAndProcess((int) ($_GET['product_id'] ?? 0), (int) ($_GET['iblock_id'] ?? 0));
        LocalRedirect('/bitrix/admin/realposterum_ai_processing_tasks.php?compare_id=' . $taskId . '&lang=' . LANGUAGE_ID);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'queue_manual') {
            $taskId = $processingService->queueProduct((int) ($_POST['product_id'] ?? 0), (int) ($_POST['iblock_id'] ?? 0));
            $message = 'Задача поставлена в очередь, ID: ' . $taskId;
        } elseif ($action === 'bulk_queue_process') {
            $processed = 0;
            foreach ((array) ($_POST['product_ids'] ?? []) as $productId) {
                $processingService->queueAndProcess((int) $productId, $settings->getCatalogIblockId());
                $processed++;
            }
            $message = 'На обработку отправлено товаров: ' . $processed;
        } elseif ($action === 'process') {
            $processingService->processTask((int) ($_POST['task_id'] ?? 0));
            $message = 'Задача обработана AI.';
        } elseif ($action === 'apply') {
            $decisionService->approve((int) ($_POST['task_id'] ?? 0), array_map('strval', (array) ($_POST['selected_fields'] ?? [])));
            $message = 'Изменения сохранены в товар.';
        } elseif ($action === 'apply_default') {
            foreach ((array) ($_POST['task_ids'] ?? []) as $taskId) {
                $decisionService->approveDefault((int) $taskId);
            }
            $message = 'Выбранные ответы применены по умолчанию.';
        } elseif ($action === 'reject') {
            $decisionService->reject((int) ($_POST['task_id'] ?? 0));
            $message = 'Ответ отклонён.';
        } elseif ($action === 'bulk_reject') {
            foreach ((array) ($_POST['task_ids'] ?? []) as $taskId) {
                $decisionService->reject((int) $taskId);
            }
            $message = 'Выбранные ответы отклонены.';
        }
    }
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $messageType = 'error';
}

$compareTaskId = (int) ($_GET['compare_id'] ?? 0);
$compareTask = $compareTaskId > 0 ? $taskRepository->findById($compareTaskId) : null;
$compareData = is_array($compareTask) ? json_decode((string) ($compareTask['PARSED_DATA_JSON'] ?? ''), true) : null;
$logs = is_array($compareTask) ? $logRepository->findByTaskId((int) $compareTask['ID']) : [];
$markedProducts = $flagProvider->findMarkedProducts();
$queueTasks = $taskRepository->findByStatuses([TaskStatus::QUEUED, TaskStatus::PROCESSING], 50);
$waitingTasks = $taskRepository->findByStatuses([TaskStatus::WAITING_CONFIRMATION], 50);
$errorTasks = $taskRepository->findByStatuses([TaskStatus::ERROR], 50);

$renderValue = static function (mixed $value): string {
    if (is_scalar($value) || $value === null) {
        return (string) $value;
    }

    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
};

$renderStatus = static function (string $status): string {
    return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:' . htmlspecialcharsbx(TaskStatus::getColor($status)) . ';color:#fff;">' . htmlspecialcharsbx(TaskStatus::getLabel($status)) . '</span>';
};

$APPLICATION->SetTitle('Очередь RealPosterum AI Processing');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($message !== null): ?>
    <div class="adm-info-message-wrap"><div class="adm-info-message<?= $messageType === 'error' ? ' adm-info-message-red' : '' ?>"><?= htmlspecialcharsbx($message) ?></div></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
    <div>
        <div class="adm-detail-title">Товары с флагом <?= htmlspecialcharsbx($settings->getNeedProcessingPropertyCode()) ?></div>
        <form method="post">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="bulk_queue_process">
            <table class="adm-list-table" width="100%">
                <thead><tr class="adm-list-table-header"><td></td><td>ID</td><td>Название</td><td>Изменён</td></tr></thead>
                <tbody>
                <?php foreach ($markedProducts as $row): ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell"><input type="checkbox" name="product_ids[]" value="<?= (int) $row['ID'] ?>"></td>
                        <td class="adm-list-table-cell">#<?= (int) $row['ID'] ?></td>
                        <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $row['NAME']) ?></td>
                        <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $row['TIMESTAMP_X']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($markedProducts === []): ?><tr><td class="adm-list-table-cell" colspan="4">Нет товаров с включённым флагом.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <p><button type="submit" class="adm-btn-save">Отправить выбранные на обработку</button></p>
        </form>

        <div class="adm-detail-title">Ручная постановка в очередь</div>
        <form method="post">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="queue_manual">
            <table class="adm-detail-content-table edit-table">
                <tr><td width="40%">ID инфоблока</td><td width="60%"><input type="number" min="1" name="iblock_id" value="<?= (int) $settings->getCatalogIblockId() ?>"></td></tr>
                <tr><td>ID товара</td><td><input type="number" min="1" name="product_id" required></td></tr>
                <tr><td></td><td><button type="submit" class="adm-btn">Поставить в очередь</button></td></tr>
            </table>
        </form>
    </div>

    <div>
        <?php if (is_array($compareTask) && is_array($compareData)): ?>
            <div class="adm-detail-title">Сравнение по задаче #<?= (int) $compareTask['ID'] ?></div>
            <div style="margin-bottom:12px;">Товар #<?= (int) $compareTask['PRODUCT_ID'] ?>, статус: <?= $renderStatus((string) $compareTask['STATUS']) ?></div>
            <?php if (!empty($compareData['summary'])): ?><div class="adm-info-message-wrap"><div class="adm-info-message"><?= htmlspecialcharsbx((string) $compareData['summary']) ?></div></div><?php endif; ?>
            <details style="margin-bottom:16px;"><summary>Что отправили</summary><textarea rows="14" cols="90" readonly><?= htmlspecialcharsbx((string) ($compareTask['REQUEST_BODY'] ?? $compareTask['MAPPED_PAYLOAD_JSON'])) ?></textarea></details>
            <form method="post">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="task_id" value="<?= (int) $compareTask['ID'] ?>">
                <table class="adm-list-table" width="100%">
                    <thead><tr class="adm-list-table-header"><td>Применить</td><td>Поле</td><td>Было</td><td>Предлагает ИИ</td></tr></thead>
                    <tbody>
                    <?php foreach ((array) ($compareData['comparison'] ?? []) as $row): ?>
                        <tr class="adm-list-table-row">
                            <td class="adm-list-table-cell"><input type="checkbox" name="selected_fields[]" value="<?= htmlspecialcharsbx((string) $row['key']) ?>"<?= in_array((string) $row['key'], (array) ($compareData['selected_by_default'] ?? []), true) ? ' checked' : '' ?>></td>
                            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $row['target_code']) ?></td>
                            <td class="adm-list-table-cell"><pre style="white-space:pre-wrap; margin:0;"><?= htmlspecialcharsbx($renderValue($row['old_value'] ?? null)) ?></pre></td>
                            <td class="adm-list-table-cell"><pre style="white-space:pre-wrap; margin:0;"><?= htmlspecialcharsbx($renderValue($row['new_value'] ?? null)) ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($compareData['comparison'])): ?><tr><td class="adm-list-table-cell" colspan="4">В ответе нет изменений, пригодных для сохранения.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="adm-btn-save">Подтвердить и сохранить</button></p>
            </form>
            <form method="post" style="margin-bottom:16px;">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="task_id" value="<?= (int) $compareTask['ID'] ?>">
                <button type="submit" class="adm-btn">Отклонить</button>
            </form>
            <?php if ($logs !== []): ?>
                <div class="adm-detail-title">Логи задачи</div>
                <table class="adm-list-table" width="100%">
                    <thead><tr class="adm-list-table-header"><td>Время</td><td>Тип</td><td>Сообщение</td></tr></thead>
                    <tbody><?php foreach ($logs as $log): ?><tr class="adm-list-table-row"><td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $log['CREATED_AT']) ?></td><td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $log['LOG_TYPE']) ?></td><td class="adm-list-table-cell"><pre style="white-space:pre-wrap; margin:0;"><?= htmlspecialcharsbx((string) $log['MESSAGE']) ?></pre></td></tr><?php endforeach; ?></tbody>
                </table>
            <?php endif; ?>
        <?php else: ?>
            <div class="adm-info-message-wrap"><div class="adm-info-message">Выберите задачу в блоке «Ожидают подтверждения», чтобы открыть сравнение old/new.</div></div>
        <?php endif; ?>
    </div>
</div>

<div class="adm-detail-title">Задачи в очереди</div>
<table class="adm-list-table" width="100%">
    <thead><tr class="adm-list-table-header"><td>ID</td><td>Товар</td><td>Статус</td><td>Последняя попытка</td><td>Ошибка</td><td>Действия</td></tr></thead>
    <tbody>
    <?php foreach ($queueTasks as $task): ?>
        <tr class="adm-list-table-row">
            <td class="adm-list-table-cell">#<?= (int) $task['ID'] ?></td>
            <td class="adm-list-table-cell">#<?= (int) $task['PRODUCT_ID'] ?></td>
            <td class="adm-list-table-cell"><?= $renderStatus((string) $task['STATUS']) ?></td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) ($task['LAST_ATTEMPT_AT'] ?? '')) ?></td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) ($task['ERROR_MESSAGE'] ?? '')) ?></td>
            <td class="adm-list-table-cell"><form method="post"><?= bitrix_sessid_post() ?><input type="hidden" name="action" value="process"><input type="hidden" name="task_id" value="<?= (int) $task['ID'] ?>"><button type="submit" class="adm-btn">Запустить / повторить</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($queueTasks === []): ?><tr><td class="adm-list-table-cell" colspan="6">Очередь пуста.</td></tr><?php endif; ?>
    </tbody>
</table>

<div class="adm-detail-title">Ожидают подтверждения</div>
<form method="post">
    <?= bitrix_sessid_post() ?>
    <table class="adm-list-table" width="100%">
        <thead><tr class="adm-list-table-header"><td></td><td>ID</td><td>Товар</td><td>Статус</td><td>Summary</td><td>Действия</td></tr></thead>
        <tbody>
        <?php foreach ($waitingTasks as $task): $parsed = json_decode((string) ($task['PARSED_DATA_JSON'] ?? ''), true); ?>
            <tr class="adm-list-table-row">
                <td class="adm-list-table-cell"><input type="checkbox" name="task_ids[]" value="<?= (int) $task['ID'] ?>"></td>
                <td class="adm-list-table-cell">#<?= (int) $task['ID'] ?></td>
                <td class="adm-list-table-cell">#<?= (int) $task['PRODUCT_ID'] ?></td>
                <td class="adm-list-table-cell"><?= $renderStatus((string) $task['STATUS']) ?></td>
                <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) ($parsed['summary'] ?? '')) ?></td>
                <td class="adm-list-table-cell"><a class="adm-btn" href="/bitrix/admin/realposterum_ai_processing_tasks.php?compare_id=<?= (int) $task['ID'] ?>&lang=<?= LANGUAGE_ID ?>">Открыть сравнение</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($waitingTasks === []): ?><tr><td class="adm-list-table-cell" colspan="6">Нет задач, ожидающих решения.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <p><button type="submit" class="adm-btn-save" name="action" value="apply_default">Массово применить по умолчанию</button> <button type="submit" class="adm-btn" name="action" value="bulk_reject">Массово отклонить</button></p>
</form>

<div class="adm-detail-title">Ошибки</div>
<table class="adm-list-table" width="100%">
    <thead><tr class="adm-list-table-header"><td>ID</td><td>Товар</td><td>Последняя попытка</td><td>Ошибка</td><td>Действия</td></tr></thead>
    <tbody>
    <?php foreach ($errorTasks as $task): ?>
        <tr class="adm-list-table-row">
            <td class="adm-list-table-cell">#<?= (int) $task['ID'] ?></td>
            <td class="adm-list-table-cell">#<?= (int) $task['PRODUCT_ID'] ?></td>
            <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) ($task['LAST_ATTEMPT_AT'] ?? '')) ?></td>
            <td class="adm-list-table-cell"><pre style="white-space:pre-wrap; margin:0;"><?= htmlspecialcharsbx((string) ($task['ERROR_MESSAGE'] ?? '')) ?></pre></td>
            <td class="adm-list-table-cell"><form method="post"><?= bitrix_sessid_post() ?><input type="hidden" name="action" value="process"><input type="hidden" name="task_id" value="<?= (int) $task['ID'] ?>"><button type="submit" class="adm-btn">Отправить повторно</button></form></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($errorTasks === []): ?><tr><td class="adm-list-table-cell" colspan="5">Ошибок нет.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
