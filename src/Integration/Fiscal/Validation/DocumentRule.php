<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/**
 * One group of field checks over a `/api/v1/documents` body.
 */
interface DocumentRule
{
    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<array{field: string, issue: string}>
     */
    public function errors(array $body): array;
}
