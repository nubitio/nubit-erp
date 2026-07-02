<?php

declare(strict_types=1);

namespace App\Sales\Port\Result;

/**
 * Domain result of creating an invoice for an existing sale.
 *
 * Intentionally HTTP-agnostic: controllers own the response shaping.
 */
final readonly class InvoiceCreationResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public mixed $detraccionMonto = null,
        public mixed $detraccionCodigo = null,
    ) {}

    public static function success(string $message, mixed $detraccionMonto = null, mixed $detraccionCodigo = null): self
    {
        return new self(true, $message, $detraccionMonto, $detraccionCodigo);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
