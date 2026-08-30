<?php

declare(strict_types=1);

namespace App\Integration\Entity;

/**
 * The handful of fields an {@see IntegrationDocument} copies off the request so
 * it is legible without re-parsing the payload. Kept out of the entity
 * constructor's argument list for clarity.
 */
final readonly class DocumentInputSummary
{
    public function __construct(
        public string $documentType,
        public string $series,
        public int $number,
    ) {}
}
