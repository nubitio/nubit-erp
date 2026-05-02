<?php

declare(strict_types=1);

namespace App\Catalog\Dto;

/**
 * Aggregated result of one product import job.
 */
final class ProductImportResult
{
    /** @param list<array{row: int, message: string}> $errors */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public array $errors = [],
    ) {
    }

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array{created: int, updated: int, errors: list<array{row: int, message: string}>} */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'errors'  => $this->errors,
        ];
    }
}
