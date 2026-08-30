<?php

declare(strict_types=1);

namespace App\Integration\Fiscal;

/**
 * The request body failed validation. Carries the per-field detail the
 * `/api/v1` error envelope surfaces; always a permanent rejection.
 */
final class InvalidDocumentInput extends \RuntimeException
{
    /**
     * @param list<array{field: string, issue: string}> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('The document payload is invalid.');
    }
}
