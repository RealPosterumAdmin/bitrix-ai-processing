<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Api\AiClient;
use RealPosterum\AiProcessing\Infrastructure\BitrixHttpClient;
use RealPosterum\AiProcessing\Repository\ProcessingLogRepository;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RealPosterum\AiProcessing\Service\FieldCatalog;
use RealPosterum\AiProcessing\Service\JsonPathResolver;
use RealPosterum\AiProcessing\Service\LogService;
use RealPosterum\AiProcessing\Service\ModuleSettings;
use RealPosterum\AiProcessing\Service\PayloadBuilder;
use RealPosterum\AiProcessing\Service\ProcessingService;
use RealPosterum\AiProcessing\Service\ProductDataExtractor;
use RealPosterum\AiProcessing\Service\ResponseParser;

$moduleId = 'realposterum.ai.processing';
if (!$USER->IsAdmin()) {
    return;
}

Loader::includeModule($moduleId);
Loader::includeModule('iblock');

$fieldCatalog = new FieldCatalog();
$jsonPathResolver = new JsonPathResolver();
$submittedData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];
$settingsService = new ModuleSettings($moduleId);
[$formData, $validationErrors] = $settingsService->normalizeAndValidate($submittedData, $fieldCatalog, $jsonPathResolver);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $formData = $settingsService->toArray();
    $validationErrors = [];
}

$message = null;
$messageType = 'ok';
$testPayload = null;
$testResponse = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    try {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($validationErrors !== []) {
            throw new RuntimeException(implode("\n", $validationErrors));
        }

        $runtimeSettings = new ModuleSettings($moduleId, $formData);
        $processingService = new ProcessingService(
            new ProcessingTaskRepository(),
            new ProductDataExtractor(),
            new PayloadBuilder($jsonPathResolver),
            new ResponseParser($jsonPathResolver),
            new AiClient(new BitrixHttpClient()),
            $runtimeSettings,
            new LogService(new ProcessingLogRepository())
        );

        if ($action === 'save') {
            $settingsService->save($formData);
            $message = 'Настройки сохранены.';
        } elseif ($action === 'test_payload') {
            $testPayload = $processingService->buildTestPayload((int) ($_POST['test_product_id'] ?? 0), (int) $formData['catalog_iblock_id']);
            $message = 'Payload успешно собран.';
        } elseif ($action === 'test_request') {
            $testResponse = $processingService->executeTestRequest((int) ($_POST['test_product_id'] ?? 0), (int) $formData['catalog_iblock_id']);
            $message = 'Тестовый запрос выполнен.';
        }
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    }
}

$iblockId = (int) ($formData['catalog_iblock_id'] ?? 0);
$elementFields = $fieldCatalog->getElementFields();
$propertyFields = $fieldCatalog->getPropertyFields($iblockId);
$computedFields = $fieldCatalog->getComputedFields();
$outboundMappings = is_array($formData['outbound_mappings'] ?? null) ? $formData['outbound_mappings'] : [];
$inboundMappings = is_array($formData['inbound_mappings'] ?? null) ? $formData['inbound_mappings'] : [];
$tabControl = new CAdminTabControl('realposterumAiProcessingOptions', [
    ['DIV' => 'general', 'TAB' => 'Общие', 'TITLE' => 'Подключение и каталог'],
    ['DIV' => 'mapping', 'TAB' => 'Маппинг', 'TITLE' => 'Исходящие и входящие маппинги'],
    ['DIV' => 'test', 'TAB' => 'Тест', 'TITLE' => 'Тестовый payload и запрос'],
]);

$renderOptions = static function (array $options, string $selected): string {
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . htmlspecialcharsbx((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . htmlspecialcharsbx((string) $label) . '</option>';
    }
    return $html;
};
?>
<?php if ($message !== null): ?>
    <div class="adm-info-message-wrap">
        <div class="adm-info-message<?= $messageType === 'error' ? ' adm-info-message-red' : '' ?>">
            <?= nl2br(htmlspecialcharsbx($message)) ?>
        </div>
    </div>
<?php endif; ?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>
    <tr><td width="40%">ID инфоблока каталога</td><td width="60%"><input type="number" min="1" name="catalog_iblock_id" value="<?= (int) $iblockId ?>" required></td></tr>
    <tr><td>Источник флага массовой обработки</td><td><select name="flag_source"><option value="property"<?= ($formData['flag_source'] ?? '') === 'property' ? ' selected' : '' ?>>Свойство инфоблока</option><option value="table"<?= ($formData['flag_source'] ?? '') === 'table' ? ' selected' : '' ?>>Таблица модуля (резерв)</option></select></td></tr>
    <tr><td>Код свойства NeedAiProcessing</td><td><input type="text" size="40" name="need_processing_property_code" value="<?= htmlspecialcharsbx((string) ($formData['need_processing_property_code'] ?? '')) ?>"></td></tr>
    <tr><td>Endpoint</td><td><input type="text" size="90" name="endpoint" value="<?= htmlspecialcharsbx((string) ($formData['endpoint'] ?? '')) ?>"></td></tr>
    <tr><td>HTTP метод</td><td><select name="http_method"><option value="POST"<?= ($formData['http_method'] ?? '') === 'POST' ? ' selected' : '' ?>>POST</option><option value="PUT"<?= ($formData['http_method'] ?? '') === 'PUT' ? ' selected' : '' ?>>PUT</option><option value="GET"<?= ($formData['http_method'] ?? '') === 'GET' ? ' selected' : '' ?>>GET</option></select></td></tr>
    <tr><td>Timeout, сек</td><td><input type="number" min="1" name="timeout" value="<?= (int) ($formData['timeout'] ?? 30) ?>"></td></tr>
    <tr><td>Тип авторизации</td><td><select name="auth_type"><option value="bearer"<?= ($formData['auth_type'] ?? '') === 'bearer' ? ' selected' : '' ?>>Bearer token</option><option value="basic"<?= ($formData['auth_type'] ?? '') === 'basic' ? ' selected' : '' ?>>Basic auth</option><option value="none"<?= ($formData['auth_type'] ?? '') === 'none' ? ' selected' : '' ?>>Без авторизации</option></select></td></tr>
    <tr><td>Token</td><td><input type="password" size="90" name="auth_token" value="<?= htmlspecialcharsbx((string) ($formData['auth_token'] ?? '')) ?>"></td></tr>
    <tr><td>Login</td><td><input type="text" size="40" name="auth_login" value="<?= htmlspecialcharsbx((string) ($formData['auth_login'] ?? '')) ?>"></td></tr>
    <tr><td>Password</td><td><input type="password" size="40" name="auth_password" value="<?= htmlspecialcharsbx((string) ($formData['auth_password'] ?? '')) ?>"></td></tr>
    <tr><td>Дополнительные headers (JSON)</td><td><textarea name="headers_json" rows="6" cols="90"><?= htmlspecialcharsbx((string) ($formData['headers_json'] ?? '')) ?></textarea></td></tr>
    <tr><td>Model</td><td><input type="text" size="40" name="model" value="<?= htmlspecialcharsbx((string) ($formData['model'] ?? '')) ?>"></td></tr>
    <tr><td>System prompt</td><td><textarea name="system_prompt" rows="5" cols="90"><?= htmlspecialcharsbx((string) ($formData['system_prompt'] ?? '')) ?></textarea></td></tr>
    <tr><td>Request template</td><td><textarea name="request_template" rows="12" cols="90"><?= htmlspecialcharsbx((string) ($formData['request_template'] ?? '')) ?></textarea><div style="margin-top: 6px; color: #666;">Используйте #PAYLOAD_JSON#, #SYSTEM_PROMPT#, #MODEL#.</div></td></tr>
    <tr><td>Путь к JSON-контенту ответа</td><td><input type="text" size="60" name="response_content_path" value="<?= htmlspecialcharsbx((string) ($formData['response_content_path'] ?? '')) ?>"></td></tr>

    <?php $tabControl->BeginNextTab(); ?>
    <tr><td colspan="2">
        <div style="font-weight: 600; margin-bottom: 8px;">Исходящие маппинги Битрикс → JSON</div>
        <table class="internal" width="100%" id="outbound-mapping-table">
            <tr class="heading"><td>Тип источника</td><td>Поле/свойство</td><td>JSON path</td><td></td></tr>
            <?php foreach ($outboundMappings as $index => $row): ?>
                <tr>
                    <td><select name="outbound_mappings[<?= (int) $index ?>][source_type]">
                        <option value="field"<?= ($row['source_type'] ?? '') === 'field' ? ' selected' : '' ?>>Поле</option>
                        <option value="property"<?= ($row['source_type'] ?? '') === 'property' ? ' selected' : '' ?>>Свойство</option>
                        <option value="computed"<?= ($row['source_type'] ?? '') === 'computed' ? ' selected' : '' ?>>Вычисляемое</option>
                    </select></td>
                    <td>
                        <select name="outbound_mappings[<?= (int) $index ?>][source_code]">
                            <optgroup label="Поля"><?= $renderOptions($elementFields, (string) ($row['source_code'] ?? '')) ?></optgroup>
                            <optgroup label="Свойства"><?= $renderOptions($propertyFields, (string) ($row['source_code'] ?? '')) ?></optgroup>
                            <optgroup label="Вычисляемые"><?= $renderOptions($computedFields, (string) ($row['source_code'] ?? '')) ?></optgroup>
                        </select>
                    </td>
                    <td><input type="text" size="50" name="outbound_mappings[<?= (int) $index ?>][json_path]" value="<?= htmlspecialcharsbx((string) ($row['json_path'] ?? '')) ?>"></td>
                    <td><button type="button" class="adm-btn" onclick="this.closest('tr').remove();">Удалить</button></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><button type="button" class="adm-btn" onclick="addOutboundRow();">Добавить строку</button></p>
    </td></tr>
    <tr><td colspan="2">
        <div style="font-weight: 600; margin-bottom: 8px;">Входящие маппинги JSON → Битрикс</div>
        <table class="internal" width="100%" id="inbound-mapping-table">
            <tr class="heading"><td>Куда сохранить</td><td>Поле/свойство</td><td>JSON path</td><td>Пустое значение</td><td></td></tr>
            <?php foreach ($inboundMappings as $index => $row): ?>
                <tr>
                    <td><select name="inbound_mappings[<?= (int) $index ?>][target_type]"><option value="field"<?= ($row['target_type'] ?? '') === 'field' ? ' selected' : '' ?>>Поле</option><option value="property"<?= ($row['target_type'] ?? '') === 'property' ? ' selected' : '' ?>>Свойство</option></select></td>
                    <td>
                        <select name="inbound_mappings[<?= (int) $index ?>][target_code]">
                            <optgroup label="Поля"><?= $renderOptions($elementFields, (string) ($row['target_code'] ?? '')) ?></optgroup>
                            <optgroup label="Свойства"><?= $renderOptions($propertyFields, (string) ($row['target_code'] ?? '')) ?></optgroup>
                        </select>
                    </td>
                    <td><input type="text" size="50" name="inbound_mappings[<?= (int) $index ?>][json_path]" value="<?= htmlspecialcharsbx((string) ($row['json_path'] ?? '')) ?>"></td>
                    <td><input type="hidden" name="inbound_mappings[<?= (int) $index ?>][allow_empty]" value="N"><input type="checkbox" name="inbound_mappings[<?= (int) $index ?>][allow_empty]" value="Y"<?= ($row['allow_empty'] ?? 'N') === 'Y' ? ' checked' : '' ?>></td>
                    <td><button type="button" class="adm-btn" onclick="this.closest('tr').remove();">Удалить</button></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><button type="button" class="adm-btn" onclick="addInboundRow();">Добавить строку</button></p>
    </td></tr>

    <?php $tabControl->BeginNextTab(); ?>
    <tr><td width="40%">ID товара для теста</td><td width="60%"><input type="number" min="1" name="test_product_id" value="<?= (int) ($_POST['test_product_id'] ?? 0) ?>"></td></tr>
    <tr><td></td><td><button type="submit" class="adm-btn" name="action" value="test_payload">Показать payload</button> <button type="submit" class="adm-btn-save" name="action" value="test_request">Выполнить тестовый запрос</button></td></tr>
    <?php if ($testPayload !== null): ?>
        <tr><td>Snapshot</td><td><textarea rows="14" cols="90" readonly><?= htmlspecialcharsbx((string) json_encode($testPayload['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea></td></tr>
        <tr><td>Mapped payload</td><td><textarea rows="14" cols="90" readonly><?= htmlspecialcharsbx((string) json_encode($testPayload['mapped_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea></td></tr>
        <tr><td>Request body</td><td><textarea rows="18" cols="90" readonly><?= htmlspecialcharsbx((string) json_encode($testPayload['request_body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea></td></tr>
    <?php endif; ?>
    <?php if ($testResponse !== null): ?>
        <tr><td>Raw response</td><td><textarea rows="18" cols="90" readonly><?= htmlspecialcharsbx((string) $testResponse['raw_response']) ?></textarea></td></tr>
        <tr><td>Parsed response</td><td><textarea rows="18" cols="90" readonly><?= htmlspecialcharsbx((string) json_encode($testResponse['parsed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea></td></tr>
    <?php endif; ?>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" class="adm-btn-save" name="action" value="save">
    <?php $tabControl->End(); ?>
</form>
<script>
let outboundIndex = <?= count($outboundMappings) ?>;
let inboundIndex = <?= count($inboundMappings) ?>;
function addOutboundRow() {
    const table = document.getElementById('outbound-mapping-table');
    const row = table.insertRow(-1);
    row.innerHTML = '<td><select name="outbound_mappings[' + outboundIndex + '][source_type]"><option value="field">Поле</option><option value="property">Свойство</option><option value="computed">Вычисляемое</option></select></td>' +
        '<td><select name="outbound_mappings[' + outboundIndex + '][source_code]"><optgroup label="Поля"><?= CUtil::JSEscape($renderOptions($elementFields, '')) ?></optgroup><optgroup label="Свойства"><?= CUtil::JSEscape($renderOptions($propertyFields, '')) ?></optgroup><optgroup label="Вычисляемые"><?= CUtil::JSEscape($renderOptions($computedFields, '')) ?></optgroup></select></td>' +
        '<td><input type="text" size="50" name="outbound_mappings[' + outboundIndex + '][json_path]"></td>' +
        '<td><button type="button" class="adm-btn" onclick="this.closest(\'tr\').remove();">Удалить</button></td>';
    outboundIndex++;
}
function addInboundRow() {
    const table = document.getElementById('inbound-mapping-table');
    const row = table.insertRow(-1);
    row.innerHTML = '<td><select name="inbound_mappings[' + inboundIndex + '][target_type]"><option value="field">Поле</option><option value="property">Свойство</option></select></td>' +
        '<td><select name="inbound_mappings[' + inboundIndex + '][target_code]"><optgroup label="Поля"><?= CUtil::JSEscape($renderOptions($elementFields, '')) ?></optgroup><optgroup label="Свойства"><?= CUtil::JSEscape($renderOptions($propertyFields, '')) ?></optgroup></select></td>' +
        '<td><input type="text" size="50" name="inbound_mappings[' + inboundIndex + '][json_path]"></td>' +
        '<td><input type="hidden" name="inbound_mappings[' + inboundIndex + '][allow_empty]" value="N"><input type="checkbox" name="inbound_mappings[' + inboundIndex + '][allow_empty]" value="Y"></td>' +
        '<td><button type="button" class="adm-btn" onclick="this.closest(\'tr\').remove();">Удалить</button></td>';
    inboundIndex++;
}
</script>
