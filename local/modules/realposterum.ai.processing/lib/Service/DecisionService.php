<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Enum\TaskStatus;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RuntimeException;

final class DecisionService
{
    public function __construct(
        private ProcessingTaskRepository $taskRepository,
        private ModuleSettings $settings,
        private LogService $logService
    ) {
    }

    /**
     * @param array<int, string> $selectedKeys
     */
    public function approve(int $taskId, array $selectedKeys): void
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
            $newValue = $row['new_value'] ?? null;
            if ($targetType === 'field') {
                $elementFields[$targetCode] = $newValue;
            } elseif ($targetType === 'property') {
                $propertyValues[$targetCode] = $newValue;
            }
        }

        if ($elementFields === [] && $propertyValues === []) {
            throw new RuntimeException('Нужно выбрать хотя бы одно изменение для применения.');
        }

        if ($elementFields !== []) {
            $element = new \CIBlockElement();
            if (!$element->Update((int) $task['PRODUCT_ID'], $elementFields)) {
                throw new RuntimeException('Не удалось обновить поля товара: ' . $element->LAST_ERROR);
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

        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, [$code => false]);
    }
}
