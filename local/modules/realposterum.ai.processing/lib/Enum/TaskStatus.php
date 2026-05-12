<?php

declare(strict_types=1);

namespace RealPosterum\AiProcessing\Enum;

final class TaskStatus
{
    public const NEW = 'NEW';
    public const QUEUED = 'QUEUED';
    public const PROCESSING = 'PROCESSING';
    public const RESPONSE_RECEIVED = 'RESPONSE_RECEIVED';
    public const WAITING_CONFIRMATION = 'WAITING_CONFIRMATION';
    public const APPLIED = 'APPLIED';
    public const REJECTED = 'REJECTED';
    public const ERROR = 'ERROR';

    /**
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [
            self::NEW,
            self::QUEUED,
            self::PROCESSING,
            self::RESPONSE_RECEIVED,
            self::WAITING_CONFIRMATION,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [self::APPLIED, self::REJECTED, self::ERROR], true);
    }

    public static function getLabel(string $status): string
    {
        return match ($status) {
            self::NEW => 'Новая',
            self::QUEUED => 'В очереди',
            self::PROCESSING => 'Обрабатывается',
            self::RESPONSE_RECEIVED => 'Ответ получен',
            self::WAITING_CONFIRMATION => 'Ждёт подтверждения',
            self::APPLIED => 'Применено',
            self::REJECTED => 'Отклонено',
            self::ERROR => 'Ошибка',
            default => $status,
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::WAITING_CONFIRMATION => '#ffc107',
            self::APPLIED => '#28a745',
            self::REJECTED => '#6c757d',
            self::ERROR => '#dc3545',
            self::PROCESSING => '#17a2b8',
            self::RESPONSE_RECEIVED => '#5c6bc0',
            default => '#7a7b7d',
        };
    }
}
