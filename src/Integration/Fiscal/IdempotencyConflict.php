<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

/**
 * The same `external_id` was submitted again with a different payload. The
 * first submission stands; this one is refused with 409.
 */
final class IdempotencyConflict extends \RuntimeException
{
    public function __construct(string $externalId)
    {
        parent::__construct(sprintf('external_id "%s" was already used with a different payload.', $externalId));
    }
}
