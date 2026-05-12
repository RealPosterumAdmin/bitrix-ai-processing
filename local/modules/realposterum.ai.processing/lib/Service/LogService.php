<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Service;

use RealPosterum\AiProcessing\Repository\ProcessingLogRepository;

final class LogService
{
    public function __construct(private ProcessingLogRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(?int $taskId, string $type, string $message, array $context = []): void
    {
        $this->repository->add($taskId, $type, 'INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(?int $taskId, string $type, string $message, array $context = []): void
    {
        $this->repository->add($taskId, $type, 'ERROR', $message, $context);
    }
}
