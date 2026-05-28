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
use RealPosterum\AiProcessing\Service\FieldCatalog;
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

if (!Loader::includeModule('iblock')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message adm-info-message-red">Не удалось подключить модуль iblock. Проверьте установку стандартного модуля инфоблоков.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$settings = new ModuleSettings($moduleId);
$taskRepository = new ProcessingTaskRepository();
$logRepository = new ProcessingLogRepository();
$logService = new LogService($logRepository);
$htmlEditorEnabled = Loader::includeModule('fileman') && function_exists('CFileMan::AddHTMLEditorFrame');
$processingService = new ProcessingService(
    $taskRepository,
    new ProductDataExtractor(),
    new PayloadBuilder(new JsonPathResolver()),
    new ResponseParser(new JsonPathResolver()),
    new AiClient(new BitrixHttpClient()),
    $settings,
    $logService
);
$flagProvider = new ProcessingFlagProvider($settings);
$fieldCatalog = new FieldCatalog();
$decisionService = new DecisionService($taskRepository, $fieldCatalog, $logService, $flagProvider);
$pathResolver = new JsonPathResolver();
$submittedEditedValues = is_array($_POST['edited_values'] ?? null) ? (array) $_POST['edited_values'] : [];

try {
    if (($_GET['action'] ?? '') === 'quick_process' && check_bitrix_sessid()) {
        $taskId = $processingService->queueAndProcess((int) ($_GET['product_id'] ?? 0), (int) ($_GET['iblock_id'] ?? 0));
        LocalRedirect('/bitrix/admin/realposterum_ai_processing_tasks.php?compare_id=' . $taskId . '&lang=' . LANGUAGE_ID);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
        if (isset($_POST['clear_product_id'])) {
            $flagProvider->clearFlag((int) $_POST['clear_product_id']);
            $message = 'Флаг снят у товара.';
        } else {
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
            } elseif ($action === 'clear_product_flags') {
                $cleared = $flagProvider->clearFlags((array) ($_POST['product_ids'] ?? []));
                $message = 'Флаг снят у товаров: ' . $cleared;
            } elseif ($action === 'process') {
                $processingService->processTask((int) ($_POST['task_id'] ?? 0));
                $message = 'Задача обработана AI.';
            } elseif ($action === 'apply') {
                $decisionService->approve(
                    (int) ($_POST['task_id'] ?? 0),
                    array_map('strval', (array) ($_POST['selected_fields'] ?? [])),
                    $submittedEditedValues
                );
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
    }
} catch (Throwable $exception) {
    $message = $exception->getMessage();
    $messageType = 'error';
}

$compareTaskId = (int) ($_GET['compare_id'] ?? 0);
$compareTask = $compareTaskId > 0 ? $taskRepository->findById($compareTaskId) : null;
$compareData = is_array($compareTask) ? json_decode((string) ($compareTask['PARSED_DATA_JSON'] ?? ''), true) : null;
$logs = is_array($compareTask) ? $logRepository->findByTaskId((int) $compareTask['ID']) : [];
$markedProducts = [];
try {
    $markedProducts = $flagProvider->findMarkedProducts();
} catch (Throwable $exception) {
    $message = $message === null ? $exception->getMessage() : $message . "\n" . $exception->getMessage();
    $messageType = 'error';
}
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

$getEditableValue = static function (mixed $value) use ($renderValue): string {
    return is_string($value) ? $value : $renderValue($value);
};

$renderEditableControl = static function (
    string $name,
    string $inputId,
    mixed $value,
    array $config
) use ($getEditableValue, $htmlEditorEnabled): string {
    $preparedValue = $getEditableValue($value);
    $escapedName = htmlspecialcharsbx($name);
    $escapedId = htmlspecialcharsbx($inputId);
    $type = (string) ($config['type'] ?? 'textarea');

    if ($type === 'html' && $htmlEditorEnabled) {
        ob_start();
        CFileMan::AddHTMLEditorFrame($name, $preparedValue, $inputId, 'html', ['width' => '100%', 'height' => 240]);
        return (string) ob_get_clean();
    }

    if ($type === 'text') {
        return '<input type="text" id="' . $escapedId . '" name="' . $escapedName . '" value="' . htmlspecialcharsbx($preparedValue) . '" style="width:100%;box-sizing:border-box;">';
    }

    $rows = $type === 'html' ? 10 : 6;
    return '<textarea id="' . $escapedId . '" name="' . $escapedName . '" rows="' . $rows . '" style="width:100%;box-sizing:border-box;">' . htmlspecialcharsbx($preparedValue) . '</textarea>';
};

$renderComparisonDetails = static function (
    string $summary,
    string $oldLabel,
    mixed $oldValue,
    string $newLabel,
    mixed $newValue
) use ($renderValue): string {
    return '<details style="margin-top:8px;">'
        . '<summary style="cursor:pointer;color:#2067b0;">' . htmlspecialcharsbx($summary) . '</summary>'
        . '<div style="margin-top:8px;"><strong>' . htmlspecialcharsbx($oldLabel) . ':</strong><pre style="white-space:pre-wrap; margin:4px 0 8px;">'
        . htmlspecialcharsbx($renderValue($oldValue))
        . '</pre></div><div><strong>' . htmlspecialcharsbx($newLabel) . ':</strong><pre style="white-space:pre-wrap; margin:4px 0 0;">'
        . htmlspecialcharsbx($renderValue($newValue))
        . '</pre></div></details>';
};

$flattenData = static function (mixed $value, string $prefix = '') use (&$flattenData): array {
    if (!is_array($value)) {
        return $prefix === '' ? [] : [$prefix => $value];
    }

    if ($value === []) {
        return $prefix === '' ? [] : [$prefix => []];
    }

    $rows = [];
    foreach ($value as $key => $child) {
        $path = $prefix === ''
            ? (string) $key
            : (is_int($key) ? $prefix . '[' . $key . ']' : $prefix . '.' . $key);

        if (is_array($child)) {
            foreach ($flattenData($child, $path) as $childPath => $childValue) {
                $rows[$childPath] = $childValue;
            }
            continue;
        }

        $rows[$path] = $child;
    }

    return $rows;
};

$getCatalogLabels = static function (FieldCatalog $catalog, int $iblockId, string $type): array {
    try {
        return match ($type) {
            'field' => $catalog->getElementFields(),
            'property' => $catalog->getPropertyFields($iblockId),
            'computed' => $catalog->getComputedFields(),
            default => [],
        };
    } catch (Throwable) {
        return [];
    }
};

$getFieldLabel = static function (FieldCatalog $catalog, int $iblockId, string $type, string $code) use ($getCatalogLabels): string {
    $labels = $getCatalogLabels($catalog, $iblockId, $type);
    return $labels[$code] ?? $code;
};

$getSnapshotValue = static function (?array $snapshotData, string $type, string $code): mixed {
    if (!is_array($snapshotData)) {
        return null;
    }

    return match ($type) {
        'field' => is_array($snapshotData['fields'] ?? null) ? ($snapshotData['fields'][$code] ?? null) : null,
        'property' => is_array($snapshotData['properties'] ?? null) ? ($snapshotData['properties'][$code] ?? null) : null,
        'computed' => is_array($snapshotData['computed'] ?? null) ? ($snapshotData['computed'][$code] ?? null) : null,
        default => null,
    };
};

$buildMappedPreviewRows = static function (
    array $mappings,
    ?array $snapshotData,
    array $data,
    int $iblockId,
    string $typeKey,
    string $codeKey
) use ($pathResolver, $getFieldLabel, $getSnapshotValue, $flattenData, $renderValue, $fieldCatalog): array {
    $rows = [];
    $usedPaths = [];

    foreach ($mappings as $mapping) {
        if (!is_array($mapping)) {
            continue;
        }

        $path = trim((string) ($mapping['json_path'] ?? ''));
        $type = trim((string) ($mapping[$typeKey] ?? ''));
        $code = trim((string) ($mapping[$codeKey] ?? ''));
        if ($path === '' || $type === '' || $code === '' || !$pathResolver->exists($data, $path)) {
            continue;
        }

        $usedPaths[$path] = true;
        $rows[] = [
            'label' => $getFieldLabel($fieldCatalog, $iblockId, $type, $code),
            'path' => $path,
            'old_value' => $getSnapshotValue($snapshotData, $type, $code),
            'new_value' => $pathResolver->get($data, $path),
        ];
    }

    foreach ($flattenData($data) as $path => $value) {
        if (isset($usedPaths[$path])) {
            continue;
        }

        $rows[] = [
            'label' => $path,
            'path' => $path,
            'old_value' => null,
            'new_value' => $value,
        ];
    }

    usort(
        $rows,
        static fn (array $left, array $right): int => strcmp((string) $left['label'], (string) $right['label'])
    );

    return array_map(
        static fn (array $row): array => $row + [
            'summary_html' => '<div><strong>Было:</strong> <pre style="white-space:pre-wrap; margin:4px 0 8px;">'
                . htmlspecialcharsbx($renderValue($row['old_value'] ?? null))
                . '</pre></div><div><strong>Пришло:</strong> <pre style="white-space:pre-wrap; margin:4px 0 0;">'
                . htmlspecialcharsbx($renderValue($row['new_value'] ?? null))
                . '</pre></div>',
        ],
        $rows
    );
};

$buildRequestPreviewRows = static function (
    array $mappings,
    ?array $snapshotData,
    array $payloadData,
    int $iblockId
) use ($pathResolver, $getFieldLabel, $getSnapshotValue, $flattenData, $renderValue, $fieldCatalog): array {
    $rows = [];
    $usedPaths = [];

    foreach ($mappings as $mapping) {
        if (!is_array($mapping)) {
            continue;
        }

        $path = trim((string) ($mapping['json_path'] ?? ''));
        $type = trim((string) ($mapping['source_type'] ?? ''));
        $code = trim((string) ($mapping['source_code'] ?? ''));
        if ($path === '' || $type === '' || $code === '' || !$pathResolver->exists($payloadData, $path)) {
            continue;
        }

        $usedPaths[$path] = true;
        $rows[] = [
            'label' => $getFieldLabel($fieldCatalog, $iblockId, $type, $code),
            'path' => $path,
            'old_value' => $getSnapshotValue($snapshotData, $type, $code),
            'new_value' => $pathResolver->get($payloadData, $path),
        ];
    }

    foreach ($flattenData($payloadData) as $path => $value) {
        if (isset($usedPaths[$path])) {
            continue;
        }

        $rows[] = [
            'label' => $path,
            'path' => $path,
            'old_value' => null,
            'new_value' => $value,
        ];
    }

    usort(
        $rows,
        static fn (array $left, array $right): int => strcmp((string) $left['label'], (string) $right['label'])
    );

    return array_map(
        static fn (array $row): array => $row + [
            'summary_html' => '<div><strong>В карточке:</strong> <pre style="white-space:pre-wrap; margin:4px 0 8px;">'
                . htmlspecialcharsbx($renderValue($row['old_value'] ?? null))
                . '</pre></div><div><strong>Отправили:</strong> <pre style="white-space:pre-wrap; margin:4px 0 0;">'
                . htmlspecialcharsbx($renderValue($row['new_value'] ?? null))
                . '</pre></div>',
        ],
        $rows
    );
};

$compareSnapshot = is_array($compareTask)
    ? json_decode((string) ($compareTask['SOURCE_SNAPSHOT_JSON'] ?? ''), true)
    : null;
$requestPayload = is_array($compareTask)
    ? json_decode((string) ($compareTask['MAPPED_PAYLOAD_JSON'] ?? ''), true)
    : null;
$responseContent = is_array($compareData['content'] ?? null) ? $compareData['content'] : null;

if ($responseContent === null && is_array($compareTask)) {
    $decodedResponse = json_decode((string) ($compareTask['RESPONSE_BODY'] ?? ''), true);
    if (is_array($decodedResponse) && $pathResolver->exists($decodedResponse, $settings->getResponseContentPath())) {
        $resolvedContent = $pathResolver->get($decodedResponse, $settings->getResponseContentPath());
        if (is_string($resolvedContent)) {
            $resolvedContent = json_decode($resolvedContent, true);
        }
        if (is_array($resolvedContent)) {
            $responseContent = $resolvedContent;
        }
    }
}

$requestPreviewRows = is_array($compareSnapshot) && is_array($requestPayload)
    ? $buildRequestPreviewRows($settings->getOutboundMappings(), $compareSnapshot, $requestPayload, (int) ($compareTask['SOURCE_IBLOCK_ID'] ?? 0))
    : [];
$responsePreviewRows = is_array($compareSnapshot) && is_array($responseContent)
    ? $buildMappedPreviewRows($settings->getInboundMappings(), $compareSnapshot, $responseContent, (int) ($compareTask['SOURCE_IBLOCK_ID'] ?? 0), 'target_type', 'target_code')
    : [];

$APPLICATION->SetTitle('Очередь RealPosterum AI Processing');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if ($message !== null): ?>
    <div class="adm-info-message-wrap"><div class="adm-info-message<?= $messageType === 'error' ? ' adm-info-message-red' : '' ?>"><?= htmlspecialcharsbx($message) ?></div></div>
<?php endif; ?>

<div style="max-width:1600px;margin:0 auto;">
    <div style="margin-bottom:24px;">
        <div class="adm-detail-title">Товары с флагом <?= htmlspecialcharsbx($settings->getNeedProcessingPropertyCode()) ?></div>
        <form method="post">
            <?= bitrix_sessid_post() ?>
            <table class="adm-list-table" width="100%">
                <thead><tr class="adm-list-table-header"><td></td><td>ID</td><td>Название</td><td>Текущее значение флага</td><td>Изменён</td><td>Действие</td></tr></thead>
                <tbody>
                <?php foreach ($markedProducts as $row): ?>
                    <tr class="adm-list-table-row">
                        <td class="adm-list-table-cell"><input type="checkbox" name="product_ids[]" value="<?= (int) $row['ID'] ?>"></td>
                        <td class="adm-list-table-cell">#<?= (int) $row['ID'] ?></td>
                        <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $row['NAME']) ?></td>
                        <td class="adm-list-table-cell">
                            <div><strong><?= htmlspecialcharsbx((string) ($row['FLAG_PROPERTY_NAME'] ?? $row['FLAG_PROPERTY_CODE'] ?? '')) ?></strong></div>
                            <pre style="white-space:pre-wrap; margin:4px 0 0;"><?= htmlspecialcharsbx($renderValue($row['FLAG_VALUE'] ?? null)) ?></pre>
                        </td>
                        <td class="adm-list-table-cell"><?= htmlspecialcharsbx((string) $row['TIMESTAMP_X']) ?></td>
                        <td class="adm-list-table-cell">
                            <button type="submit" class="adm-btn" name="clear_product_id" value="<?= (int) $row['ID'] ?>">Снять флаг</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($markedProducts === []): ?><tr><td class="adm-list-table-cell" colspan="6">Нет товаров с включённым флагом.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <p><button type="submit" class="adm-btn-save" name="action" value="bulk_queue_process">Отправить выбранные на обработку</button> <button type="submit" class="adm-btn" name="action" value="clear_product_flags">Снять флаг у выбранных</button></p>
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

    <div style="margin-bottom:24px;">
        <?php if (is_array($compareTask) && is_array($compareData)): ?>
            <div class="adm-detail-title">Сравнение по задаче #<?= (int) $compareTask['ID'] ?></div>
            <div style="margin-bottom:12px;">Товар #<?= (int) $compareTask['PRODUCT_ID'] ?>, статус: <?= $renderStatus((string) $compareTask['STATUS']) ?></div>
            <?php if (!empty($compareData['summary'])): ?><div class="adm-info-message-wrap"><div class="adm-info-message"><?= htmlspecialcharsbx((string) $compareData['summary']) ?></div></div><?php endif; ?>
            <div class="adm-detail-title">Что ответил сервис</div>
            <details open style="margin-bottom:16px;">
                <summary>Точный ответ сервиса</summary>
                <textarea rows="18" readonly aria-label="Точный ответ сервиса" style="width:100%;box-sizing:border-box;"><?= htmlspecialcharsbx((string) ($compareTask['RESPONSE_BODY'] ?? '')) ?></textarea>
                <?php if (is_array($responseContent)): ?>
                    <div style="margin-top:12px;">
                        <div style="font-weight:600; margin-bottom:6px;">Распарсенный JSON ответа</div>
                        <textarea rows="18" readonly aria-label="Распарсенный JSON ответа" style="width:100%;box-sizing:border-box;"><?= htmlspecialcharsbx((string) json_encode($responseContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea>
                    </div>
                <?php endif; ?>
            </details>
            <details open style="margin-bottom:16px;">
                <summary>Что отправили</summary>
                <table class="adm-list-table" width="100%" style="margin:12px 0;">
                    <thead><tr class="adm-list-table-header"><td width="32%">Поле</td><td width="68%">Что было и что отправили</td></tr></thead>
                    <tbody>
                    <?php foreach ($requestPreviewRows as $row): ?>
                        <tr class="adm-list-table-row">
                            <td class="adm-list-table-cell">
                                <strong><?= htmlspecialcharsbx((string) $row['label']) ?></strong><br>
                                <span style="color:#666;"><?= htmlspecialcharsbx((string) $row['path']) ?></span>
                            </td>
                            <td class="adm-list-table-cell"><?= $row['summary_html'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($requestPreviewRows === []): ?><tr><td class="adm-list-table-cell" colspan="2">Не удалось подготовить человекочитаемое представление запроса.</td></tr><?php endif; ?>
                    </tbody>
                </table>
                <div style="font-weight:600; margin-bottom:6px;">Точный запрос</div>
                <textarea rows="14" readonly aria-label="Что отправили в AI" style="width:100%;box-sizing:border-box;"><?= htmlspecialcharsbx((string) ($compareTask['REQUEST_BODY'] ?? $compareTask['MAPPED_PAYLOAD_JSON'])) ?></textarea>
            </details>
            <?php if (!empty($compareData['comparison'])): ?>
                <form method="post">
                    <?= bitrix_sessid_post() ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="task_id" value="<?= (int) $compareTask['ID'] ?>">
                    <table class="adm-list-table" width="100%">
                        <thead><tr class="adm-list-table-header"><td width="6%">Применить</td><td width="24%">Поле</td><td width="70%">Ответ сервиса / редактор</td></tr></thead>
                        <tbody>
                        <?php foreach ((array) ($compareData['comparison'] ?? []) as $index => $row): ?>
                            <?php
                            $comparisonKey = (string) ($row['key'] ?? '');
                            $controlConfig = $fieldCatalog->getInputControl(
                                (int) ($compareTask['SOURCE_IBLOCK_ID'] ?? 0),
                                (string) ($row['target_type'] ?? ''),
                                (string) ($row['target_code'] ?? '')
                            );
                            $editedValue = array_key_exists($comparisonKey, $submittedEditedValues)
                                ? $submittedEditedValues[$comparisonKey]
                                : ($row['new_value'] ?? null);
                            $inputName = 'edited_values[' . $comparisonKey . ']';
                            $inputId = 'edited_' . preg_replace('/[^a-z0-9_]+/i', '_', $comparisonKey);
                            ?>
                            <tr class="adm-list-table-row">
                                <td class="adm-list-table-cell" style="vertical-align:top;"><input type="checkbox" name="selected_fields[]" value="<?= htmlspecialcharsbx($comparisonKey) ?>"<?= in_array($comparisonKey, (array) ($compareData['selected_by_default'] ?? []), true) ? ' checked' : '' ?>></td>
                                <td class="adm-list-table-cell" style="vertical-align:top;">
                                    <strong><?= (int) $index + 1 ?>. <?= htmlspecialcharsbx($getFieldLabel($fieldCatalog, (int) ($compareTask['SOURCE_IBLOCK_ID'] ?? 0), (string) ($row['target_type'] ?? ''), (string) ($row['target_code'] ?? ''))) ?></strong><br>
                                    <span style="color:#666;"><?= htmlspecialcharsbx((string) ($row['target_code'] ?? '')) ?></span>
                                </td>
                                <td class="adm-list-table-cell" style="vertical-align:top;">
                                    <?= $renderEditableControl($inputName, $inputId, $editedValue, $controlConfig) ?>
                                    <?= $renderComparisonDetails('Показать что было', 'В карточке', $row['old_value'] ?? null, 'Ответ сервиса', $row['new_value'] ?? null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><button type="submit" class="adm-btn-save">Подтвердить и сохранить</button></p>
                </form>
            <?php else: ?>
                <?php if ($responsePreviewRows !== []): ?>
                    <table class="adm-list-table" width="100%" style="margin-bottom:16px;">
                        <thead><tr class="adm-list-table-header"><td width="32%">Поле</td><td width="68%">Ответ сервиса</td></tr></thead>
                        <tbody>
                        <?php foreach ($responsePreviewRows as $row): ?>
                            <tr class="adm-list-table-row">
                                <td class="adm-list-table-cell">
                                    <strong><?= htmlspecialcharsbx((string) $row['label']) ?></strong><br>
                                    <span style="color:#666;"><?= htmlspecialcharsbx((string) $row['path']) ?></span>
                                </td>
                                <td class="adm-list-table-cell">
                                    <pre style="white-space:pre-wrap; margin:0;"><?= htmlspecialcharsbx($renderValue($row['new_value'] ?? null)) ?></pre>
                                    <?= $renderComparisonDetails('Показать что было', 'В карточке', $row['old_value'] ?? null, 'Ответ сервиса', $row['new_value'] ?? null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div class="adm-info-message-wrap"><div class="adm-info-message">Автоматически сопоставленных изменений нет. Ответ сервиса и отправленный запрос раскрыты выше, а по кнопке «Показать что было» видно исходные данные товара для сравнения.</div></div>
            <?php endif; ?>
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
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
