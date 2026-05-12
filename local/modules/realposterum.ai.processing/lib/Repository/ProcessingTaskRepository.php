<?php

namespace RealPosterum\AiProcessing\Repository;

use Bitrix\Main\Type\DateTime;
use RealPosterum\AiProcessing\Table\ProcessingTaskTable;
use RuntimeException;

class ProcessingTaskRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function add(int $productId, int $iblockId, array $payload, string $promptText): int
    {
        $now = new DateTime();
        $result = ProcessingTaskTable::add([
            'PRODUCT_ID' => $productId,
            'SOURCE_IBLOCK_ID' => $iblockId,
            'STATUS' => 'new',
            'PAYLOAD_JSON' => $this->encode($payload),
            'PROMPT_TEXT' => $promptText,
            'CREATED_AT' => $now,
            'UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int) $result->getId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $taskId): ?array
    {
        $row = ProcessingTaskTable::getByPrimary($taskId)->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(int $limit): array
    {
        return ProcessingTaskTable::getList([
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
        ])->fetchAll();
    }

    /**
     * @param array<string, mixed> $result
     */
    public function markPendingReview(int $taskId, array $result): void
    {
        $this->update($taskId, [
            'STATUS' => 'pending_review',
            'RESULT_JSON' => $this->encode($result),
            'ERROR_MESSAGE' => null,
            'PROCESSED_AT' => new DateTime(),
        ]);
    }

    public function markProcessing(int $taskId): void
    {
        $this->update($taskId, ['STATUS' => 'processing']);
    }

    public function markApplied(int $taskId): void
    {
        $this->update($taskId, [
            'STATUS' => 'applied',
            'DECIDED_AT' => new DateTime(),
        ]);
    }

    public function markRejected(int $taskId): void
    {
        $this->update($taskId, [
            'STATUS' => 'rejected',
            'DECIDED_AT' => new DateTime(),
        ]);
    }

    public function markFailed(int $taskId, string $message): void
    {
        $this->update($taskId, [
            'STATUS' => 'failed',
            'ERROR_MESSAGE' => $message,
            'PROCESSED_AT' => new DateTime(),
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function update(int $taskId, array $fields): void
    {
        $fields['UPDATED_AT'] = new DateTime();
        $result = ProcessingTaskTable::update($taskId, $fields);
        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
