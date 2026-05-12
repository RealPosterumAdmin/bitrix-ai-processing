<?php

namespace RealPosterum\AiProcessing\Enum;

final class TaskStatus
{
    public const NEW = 'new';
    public const PROCESSING = 'processing';
    public const PENDING_REVIEW = 'pending_review';
    public const APPLIED = 'applied';
    public const REJECTED = 'rejected';
    public const FAILED = 'failed';
}
