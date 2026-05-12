<?php

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Api\AiClient;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RuntimeException;
use Throwable;

class ProcessingService
{
    public function __construct(
        private ProcessingTaskRepository $repository,
        private ProductSnapshotBuilder $snapshotBuilder,
        private PromptBuilder $promptBuilder,
        private AiClient $client
    ) {
    }

    public function queueProduct(int $productId, int $iblockId): int
    {
        $snapshot = $this->snapshotBuilder->build($productId, $iblockId);
        $payload = $snapshot->toArray();
        $prompt = $this->promptBuilder->build($snapshot);

        return $this->repository->add($productId, $iblockId, $payload, $prompt);
    }

    public function processTask(int $taskId): void
    {
        $task = $this->repository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        $payload = json_decode((string) $task['PAYLOAD_JSON'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Невозможно прочитать снимок товара из задачи.');
        }

        $prompt = trim((string) ($task['PROMPT_TEXT'] ?? ''));
        if ($prompt === '') {
            throw new RuntimeException('У задачи отсутствует prompt.');
        }

        $this->repository->markProcessing($taskId);

        try {
            $result = $this->client->processProduct($payload, $prompt);
            $this->repository->markPendingReview($taskId, $result->toArray());
        } catch (Throwable $exception) {
            $this->repository->markFailed($taskId, $exception->getMessage());
            throw $exception;
        }
    }
}
