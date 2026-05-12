<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

$moduleId = 'realposterum.ai.processing';

if (!$USER->IsAdmin()) {
    return;
}

Loader::includeModule($moduleId);

$settings = [
    'api_base_url' => [
        'label' => 'AI API URL',
        'default' => 'https://api.openai.com/v1/chat/completions',
    ],
    'api_token' => [
        'label' => 'AI API token',
        'default' => '',
    ],
    'model' => [
        'label' => 'Модель',
        'default' => 'gpt-4.1-mini',
    ],
    'system_prompt' => [
        'label' => 'Системный промпт',
        'default' => 'Ты помогаешь улучшать карточки товаров в каталоге Bitrix и должен возвращать только JSON.',
    ],
    'default_prompt_template' => [
        'label' => 'Шаблон пользовательского промпта',
        'default' => "Проанализируй товар и верни JSON со структурой fields, properties, summary.\nТовар:\n#PRODUCT_JSON#",
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    foreach ($settings as $name => $definition) {
        Option::set($moduleId, $name, trim((string) ($_POST[$name] ?? $definition['default'])));
    }
}

$tabControl = new CAdminTabControl('realposterumAiProcessingOptions', [
    [
        'DIV' => 'main',
        'TAB' => 'Настройки',
        'TITLE' => 'Настройки подключения к AI и шаблонов запросов',
    ],
]);

$tabControl->Begin();
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->BeginNextTab();
    foreach ($settings as $name => $definition) {
        $value = Option::get($moduleId, $name, $definition['default']);
        ?>
        <tr>
            <td width="40%"><?= htmlspecialcharsbx($definition['label']) ?></td>
            <td width="60%">
                <?php if (in_array($name, ['system_prompt', 'default_prompt_template'], true)): ?>
                    <textarea name="<?= htmlspecialcharsbx($name) ?>" rows="6" cols="80"><?= htmlspecialcharsbx($value) ?></textarea>
                <?php else: ?>
                    <input type="<?= $name === 'api_token' ? 'password' : 'text' ?>" size="80" name="<?= htmlspecialcharsbx($name) ?>" value="<?= htmlspecialcharsbx($value) ?>">
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    $tabControl->Buttons();
    ?>
    <input type="submit" class="adm-btn-save" value="Сохранить">
</form>
<?php
$tabControl->End();
