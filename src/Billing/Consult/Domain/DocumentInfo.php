<?php

declare(strict_types=1);

namespace App\Billing\Consult\Domain;

/**
 * Value object returned by any document lookup (RUC or DNI).
 */
final readonly class DocumentInfo
{
    public function __construct(
        /** The document number (RUC or DNI). */
        public string $id,
        /** Full name or company name (razón social / nombres completos). */
        public string $name,
        /** Fiscal address. Empty string for DNI (RENIEC does not expose address). */
        public string $address,
        /** Whether the taxpayer/person record is active. */
        public bool $active,
    ) {}
}
