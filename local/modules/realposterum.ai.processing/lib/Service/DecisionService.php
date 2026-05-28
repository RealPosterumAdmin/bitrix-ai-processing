<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use Bitrix\Main\Loader;
use RealPosterum\AiProcessing\Enum\TaskStatus;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RuntimeException;

final class DecisionService
{
    public function __construct(
        private ProcessingTaskRepository $taskRepository,
        private ModuleSettings $settings,
        private FieldCatalog $fieldCatalog,
        private LogService $logService
    ) {
    }

    /**
     * @param array<int, string> $selectedKeys
     * @param array<string, mixed> $editedValues
     */
    public function approve(int $taskId, array $selectedKeys, array $editedValues = []): void
    {
        $task = $this->taskRepository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        if ((string) $task['STATUS'] === TaskStatus::APPLIED) {
            throw new RuntimeException('Этот ответ уже применён.');
        }

        $parsed = json_decode((string) ($task['PARSED_DATA_JSON'] ?? ''), true);
        if (!is_array($parsed) || !is_array($parsed['comparison'] ?? null)) {
            throw new RuntimeException('У задачи нет валидного результата для применения.');
        }

        $selectedLookup = array_fill_keys($selectedKeys, true);
        $editedLookup = [];
        foreach ($editedValues as $key => $value) {
            $editedLookup[(string) $key] = $value;
        }

        $elementFields = [];
        $propertyValues = [];
        foreach ($parsed['comparison'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || !isset($selectedLookup[$key])) {
                continue;
            }

            $targetType = (string) ($row['target_type'] ?? '');
            $targetCode = (string) ($row['target_code'] ?? '');
            $newValue = $this->resolveNewValue($row['new_value'] ?? null, $editedLookup[$key] ?? null, array_key_exists($key, $editedLookup));
            $preparedValue = $this->fieldCatalog->prepareValueForSave((int) $task['SOURCE_IBLOCK_ID'], $targetType, $targetCode, $newValue);
            $elementFields = array_merge($elementFields, $preparedValue['fields']);
            $propertyValues = array_merge($propertyValues, $preparedValue['properties']);
        }

        if ($elementFields === [] && $propertyValues === []) {
            throw new RuntimeException('Нужно выбрать хотя бы одно изменение для применения.');
        }

        $this->ensureIblockModule();

        if ($elementFields !== []) {
            $element = new \CIBlockElement();
            if (!$element->Update((int) $task['PRODUCT_ID'], $elementFields)) {
                $this->logService->error($taskId, 'apply_error', 'Ошибка обновления полей товара.', [
                    'bitrix_error' => (string) $element->LAST_ERROR,
                ]);
                throw new RuntimeException('Не удалось обновить поля товара. Подробности сохранены в логе задачи.');
            }
        }

        if ($propertyValues !== []) {
            \CIBlockElement::SetPropertyValuesEx((int) $task['PRODUCT_ID'], (int) $task['SOURCE_IBLOCK_ID'], $propertyValues);
        }

        $this->clearNeedProcessingFlag((int) $task['PRODUCT_ID'], (int) $task['SOURCE_IBLOCK_ID']);
        $this->taskRepository->markApplied($taskId, $selectedKeys);
        $this->logService->info($taskId, 'apply', 'Изменения AI применены.', ['selected' => $selectedKeys]);
    }

    public function approveDefault(int $taskId): void
    {
        $task = $this->taskRepository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        $parsed = json_decode((string) ($task['PARSED_DATA_JSON'] ?? ''), true);
        if (!is_array($parsed)) {
            throw new RuntimeException('Нет данных для применения.');
        }

        $selected = is_array($parsed['selected_by_default'] ?? null) ? $parsed['selected_by_default'] : [];
        $this->approve($taskId, array_map('strval', $selected));
    }

    public function reject(int $taskId): void
    {
        $task = $this->taskRepository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        if ((string) $task['STATUS'] === TaskStatus::APPLIED) {
            throw new RuntimeException('Применённую задачу нельзя отклонить.');
        }

        $this->clearNeedProcessingFlag((int) $task['PRODUCT_ID'], (int) $task['SOURCE_IBLOCK_ID']);
        $this->taskRepository->markRejected($taskId);
        $this->logService->info($taskId, 'reject', 'AI-ответ отклонён.');
    }

    private function clearNeedProcessingFlag(int $productId, int $iblockId): void
    {
        if ($this->settings->getFlagSource() !== 'property') {
            return;
        }

        $code = $this->settings->getNeedProcessingPropertyCode();
        if ($code === '') {
            return;
        }

        $this->ensureIblockModule();
        $property = $this->fieldCatalog->getPropertyMetadata($iblockId, $code);
        $emptyValue = false;
        if (is_array($property)) {
            if ((string) ($property['PROPERTY_TYPE'] ?? '') === 'S' && strtoupper((string) ($property['USER_TYPE'] ?? '')) === 'HTML') {
                $emptyValue = ['VALUE' => ['TEXT' => '', 'TYPE' => 'html']];
            } elseif ((string) ($property['MULTIPLE'] ?? 'N') === 'Y') {
                $emptyValue = [];
            } else {
                $emptyValue = '';
            }
        }

        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$code => $emptyValue]);
    }

    private function ensureIblockModule(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new RuntimeException('Не удалось подключить модуль iblock.');
        }
    }

    private function resolveNewValue(mixed $defaultValue, mixed $editedValue, bool $hasEditedValue): mixed
    {
        if (!$hasEditedValue) {
            return $defaultValue;
        }

        if (is_array($editedValue)) {
            return $editedValue;
        }

        if (is_array($defaultValue) && is_string($editedValue)) {
            $decoded = json_decode($editedValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $editedValue;
    }
}
