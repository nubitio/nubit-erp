<?php

declare(strict_types=1);

namespace App\Sales\Port\Result;

/**
 * Domain result of replacing a rejected electronic document for a sale.
 *
 * Intentionally HTTP-agnostic: controllers own the response shaping.
 */
final readonly class RejectedDocumentReplacementResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $newId = null,
    ) {}

    public static function success(string $message, ?string $newId = null): self
    {
        return new self(true, $message, $newId);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
