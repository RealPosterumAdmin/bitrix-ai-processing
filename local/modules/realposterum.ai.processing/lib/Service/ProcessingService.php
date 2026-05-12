<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Api\AiClient;
use RealPosterum\AiProcessing\Enum\TaskStatus;
use RealPosterum\AiProcessing\Model\ProductSnapshot;
use RealPosterum\AiProcessing\Repository\ProcessingTaskRepository;
use RuntimeException;
use Throwable;

final class ProcessingService
{
    public function __construct(
        private ProcessingTaskRepository $taskRepository,
        private ProductDataExtractor $extractor,
        private PayloadBuilder $payloadBuilder,
        private ResponseParser $responseParser,
        private AiClient $client,
        private ModuleSettings $settings,
        private LogService $logService
    ) {
    }

    public function queueProduct(int $productId, int $iblockId): int
    {
        $existing = $this->taskRepository->findOpenByProduct($productId, $iblockId);
        if ($existing !== null) {
            return (int) $existing['ID'];
        }

        $snapshot = $this->extractor->buildSnapshot($productId, $iblockId);
        $mappedPayload = $this->payloadBuilder->build($snapshot, $this->settings->getOutboundMappings());
        $idempotencyKey = sha1(json_encode([
            'product' => $productId,
            'iblock' => $iblockId,
            'payload' => $mappedPayload,
            'endpoint' => $this->settings->getEndpoint(),
            'inbound' => $this->settings->getInboundMappings(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $taskId = $this->taskRepository->add([
            'PRODUCT_ID' => $productId,
            'SOURCE_IBLOCK_ID' => $iblockId,
            'IDEMPOTENCY_KEY' => $idempotencyKey,
            'SOURCE_SNAPSHOT_JSON' => $this->taskRepository->encode($snapshot->toArray()),
            'MAPPED_PAYLOAD_JSON' => $this->taskRepository->encode($mappedPayload),
        ]);

        $this->logService->info($taskId, 'queue', 'Задача поставлена в очередь.', [
            'product_id' => $productId,
            'iblock_id' => $iblockId,
            'idempotency_key' => $idempotencyKey,
        ]);

        return $taskId;
    }

    public function queueAndProcess(int $productId, int $iblockId): int
    {
        $existing = $this->taskRepository->findOpenByProduct($productId, $iblockId);
        if ($existing !== null) {
            $status = (string) $existing['STATUS'];
            if (in_array($status, [TaskStatus::WAITING_CONFIRMATION, TaskStatus::PROCESSING], true)) {
                return (int) $existing['ID'];
            }
        }

        $taskId = $this->queueProduct($productId, $iblockId);
        $task = $this->taskRepository->findById($taskId);
        if ($task !== null && in_array((string) $task['STATUS'], [TaskStatus::QUEUED, TaskStatus::ERROR, TaskStatus::NEW], true)) {
            $this->processTask($taskId);
        }

        return $taskId;
    }

    public function processTask(int $taskId): void
    {
        $task = $this->taskRepository->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена.');
        }

        if (!in_array((string) $task['STATUS'], [TaskStatus::QUEUED, TaskStatus::ERROR, TaskStatus::NEW], true)) {
            throw new RuntimeException('Задачу можно отправить повторно только из статусов очереди или ошибки.');
        }

        $snapshot = $this->restoreSnapshot((string) $task['SOURCE_SNAPSHOT_JSON']);
        $mappedPayload = $this->decodeArray((string) $task['MAPPED_PAYLOAD_JSON'], 'Не удалось прочитать payload задачи.');

        $this->taskRepository->markProcessing($taskId);
        $this->logService->info($taskId, 'process', 'Старт отправки во внешний AI API.');

        try {
            $requestBody = $this->payloadBuilder->renderRequestBody($mappedPayload, $this->settings);
            $apiResult = $this->client->send($this->settings, $requestBody);
            $parsedData = $this->responseParser->parse((array) $apiResult['decoded'], $snapshot, $this->settings);

            $this->taskRepository->markWaitingConfirmation(
                $taskId,
                (string) $apiResult['method'],
                (string) $apiResult['endpoint'],
                $this->taskRepository->encode((array) $apiResult['headers']),
                (string) $apiResult['request_body'],
                (string) $apiResult['response_body'],
                $parsedData
            );

            $this->logService->info($taskId, 'api_request', 'Запрос в AI API выполнен.', [
                'endpoint' => $apiResult['endpoint'],
                'method' => $apiResult['method'],
                'headers' => $apiResult['headers'],
            ]);
            $this->logService->info($taskId, 'api_response', 'Ответ AI API сохранён.', [
                'summary' => $parsedData['summary'] ?? '',
                'comparison_count' => is_array($parsedData['comparison'] ?? null) ? count($parsedData['comparison']) : 0,
            ]);
        } catch (Throwable $exception) {
            $this->taskRepository->markError($taskId, $exception->getMessage());
            $this->logService->error($taskId, 'process_error', $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTestPayload(int $productId, int $iblockId): array
    {
        $snapshot = $this->extractor->buildSnapshot($productId, $iblockId);
        $mappedPayload = $this->payloadBuilder->build($snapshot, $this->settings->getOutboundMappings());

        return [
            'snapshot' => $snapshot->toArray(),
            'mapped_payload' => $mappedPayload,
            'request_body' => json_decode($this->payloadBuilder->renderRequestBody($mappedPayload, $this->settings), true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executeTestRequest(int $productId, int $iblockId): array
    {
        $snapshot = $this->extractor->buildSnapshot($productId, $iblockId);
        $mappedPayload = $this->payloadBuilder->build($snapshot, $this->settings->getOutboundMappings());
        $requestBody = $this->payloadBuilder->renderRequestBody($mappedPayload, $this->settings);
        $apiResult = $this->client->send($this->settings, $requestBody);
        $parsedData = $this->responseParser->parse((array) $apiResult['decoded'], $snapshot, $this->settings);

        return [
            'request_body' => json_decode($requestBody, true),
            'raw_response' => (string) $apiResult['response_body'],
            'parsed' => $parsedData,
        ];
    }

    private function restoreSnapshot(string $json): ProductSnapshot
    {
        $data = $this->decodeArray($json, 'Не удалось прочитать snapshot товара.');
        return ProductSnapshot::fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArray(string $json, string $errorMessage): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException($errorMessage);
        }

        return $decoded;
    }
}
