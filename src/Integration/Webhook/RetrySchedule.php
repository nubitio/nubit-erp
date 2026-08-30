<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

/**
 * Back-off between delivery attempts. After the last entry the delivery is
 * dead-lettered.
 */
final class RetrySchedule
{
    /** @var list<int> seconds to wait before attempt N+1 */
    private const array DELAYS = [60, 300, 1800, 7200, 18000, 36000, 86400, 86400];

    public function nextDelaySeconds(int $completedAttempts): ?int
    {
        return self::DELAYS[$completedAttempts] ?? null;
    }

    public function nextRetryAt(int $completedAttempts, \DateTimeImmutable $from): ?\DateTimeImmutable
    {
        $delay = $this->nextDelaySeconds($completedAttempts);

        return null === $delay ? null : $from->add(new \DateInterval('PT' . $delay . 'S'));
    }

    public function maxAttempts(): int
    {
        return count(self::DELAYS) + 1;
    }
}
