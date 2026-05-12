<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Repository;

use Bitrix\Main\Type\DateTime;
use RealPosterum\AiProcessing\Enum\TaskStatus;
use RealPosterum\AiProcessing\Table\ProcessingTaskTable;
use RuntimeException;

final class ProcessingTaskRepository
{
    private const MAX_ERROR_MESSAGE_LENGTH = 65535;
    private const TRUNCATION_SUFFIX = '...[truncated]';

    /**
     * @param array<string, mixed> $fields
     */
    public function add(array $fields): int
    {
        $now = new DateTime();
        $result = ProcessingTaskTable::add($fields + [
            'STATUS' => TaskStatus::QUEUED,
            'CREATED_AT' => $now,
            'UPDATED_AT' => $now,
            'LAST_ATTEMPT_AT' => null,
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
     * @return array<string, mixed>|null
     */
    public function findOpenByProduct(int $productId, int $iblockId): ?array
    {
        $row = ProcessingTaskTable::getList([
            'filter' => [
                '=PRODUCT_ID' => $productId,
                '=SOURCE_IBLOCK_ID' => $iblockId,
                '@STATUS' => TaskStatus::active(),
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<int, string> $statuses
     * @return array<int, array<string, mixed>>
     */
    public function findByStatuses(array $statuses, int $limit = 50): array
    {
        return ProcessingTaskTable::getList([
            'filter' => ['@STATUS' => $statuses],
            'order' => ['UPDATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => $limit,
        ])->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(int $limit = 50): array
    {
        return ProcessingTaskTable::getList([
            'order' => ['UPDATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => $limit,
        ])->fetchAll();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $taskId, array $fields): void
    {
        $fields['UPDATED_AT'] = new DateTime();
        $result = ProcessingTaskTable::update($taskId, $fields);
        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function markQueued(int $taskId): void
    {
        $this->update($taskId, ['STATUS' => TaskStatus::QUEUED, 'ERROR_MESSAGE' => null]);
    }

    public function markProcessing(int $taskId): void
    {
        $this->update($taskId, [
            'STATUS' => TaskStatus::PROCESSING,
            'LAST_ATTEMPT_AT' => new DateTime(),
            'ERROR_MESSAGE' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $parsedData
     */
    public function markWaitingConfirmation(
        int $taskId,
        string $requestMethod,
        string $endpoint,
        string $requestHeadersJson,
        string $requestBody,
        string $responseBody,
        array $parsedData
    ): void {
        $now = new DateTime();
        $this->update($taskId, [
            'STATUS' => TaskStatus::WAITING_CONFIRMATION,
            'REQUEST_METHOD' => $requestMethod,
            'ENDPOINT' => $endpoint,
            'REQUEST_HEADERS_JSON' => $requestHeadersJson,
            'REQUEST_BODY' => $requestBody,
            'RESPONSE_BODY' => $responseBody,
            'PARSED_DATA_JSON' => $this->encode($parsedData),
            'ERROR_MESSAGE' => null,
            'RESPONSE_RECEIVED_AT' => $now,
            'LAST_ATTEMPT_AT' => $now,
        ]);
    }

    /**
     * @param array<int, string> $selectedFields
     */
    public function markApplied(int $taskId, array $selectedFields): void
    {
        $this->update($taskId, [
            'STATUS' => TaskStatus::APPLIED,
            'SELECTED_FIELDS_JSON' => $this->encode($selectedFields),
            'APPLIED_AT' => new DateTime(),
        ]);
    }

    public function markRejected(int $taskId): void
    {
        $this->update($taskId, [
            'STATUS' => TaskStatus::REJECTED,
            'REJECTED_AT' => new DateTime(),
        ]);
    }

    public function markError(int $taskId, string $message): void
    {
        $truncatedMessage = mb_strlen($message) > self::MAX_ERROR_MESSAGE_LENGTH
            ? mb_substr($message, 0, self::MAX_ERROR_MESSAGE_LENGTH - mb_strlen(self::TRUNCATION_SUFFIX)) . self::TRUNCATION_SUFFIX
            : $message;

        $this->update($taskId, [
            'STATUS' => TaskStatus::ERROR,
            'ERROR_MESSAGE' => $truncatedMessage,
            'LAST_ATTEMPT_AT' => new DateTime(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
