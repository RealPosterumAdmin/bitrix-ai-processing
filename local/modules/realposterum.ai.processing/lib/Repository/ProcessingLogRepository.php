<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Repository;

use Bitrix\Main\Type\DateTime;
use RealPosterum\AiProcessing\Table\ProcessingLogTable;
use RuntimeException;

final class ProcessingLogRepository
{
    /**
     * @param array<string, mixed> $context
     */
    public function add(?int $taskId, string $logType, string $level, string $message, array $context = []): void
    {
        $result = ProcessingLogTable::add([
            'TASK_ID' => $taskId,
            'LOG_TYPE' => $logType,
            'LEVEL' => $level,
            'MESSAGE' => $message,
            'CONTEXT_JSON' => $context === [] ? null : $this->encode($context),
            'CREATED_AT' => new DateTime(),
        ]);

        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByTaskId(int $taskId): array
    {
        return ProcessingLogTable::getList([
            'filter' => ['=TASK_ID' => $taskId],
            'order' => ['ID' => 'DESC'],
        ])->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
