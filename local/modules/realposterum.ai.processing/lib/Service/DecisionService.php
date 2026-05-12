<?php

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RuntimeException;

class DecisionService
{
    public function __construct(private ProcessingTaskRepository $repository)
    {
    }

    public function approve(int $taskId): void
    {
        $task = $this->repository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        $result = json_decode((string) ($task['RESULT_JSON'] ?? ''), true);
        if (!is_array($result)) {
            throw new RuntimeException('У задачи нет AI-результата для применения.');
        }

        $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];
        $properties = is_array($result['properties'] ?? null) ? $result['properties'] : [];

        if ($fields !== []) {
            $element = new \CIBlockElement();
            $element->Update((int) $task['PRODUCT_ID'], $fields);
        }

        if ($properties !== []) {
            \CIBlockElement::SetPropertyValuesEx(
                (int) $task['PRODUCT_ID'],
                (int) $task['SOURCE_IBLOCK_ID'],
                $properties
            );
        }

        $this->repository->markApplied($taskId);
    }

    public function reject(int $taskId): void
    {
        if ($this->repository->findById($taskId) === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        $this->repository->markRejected($taskId);
    }
}
