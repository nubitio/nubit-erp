<?php

declare(strict_types=1);

namespace App\Efact\RateLimit;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $limit,
        public int $remaining,
        public int $retryAfter,
    ) {
    }
}
