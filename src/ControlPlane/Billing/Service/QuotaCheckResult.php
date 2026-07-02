<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Service;

final readonly class QuotaCheckResult
{
    public function __construct(
        public bool $allowed,
        public string $resource,
        public int $current,
        public int $limit,
    ) {}
}
