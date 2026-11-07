<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

/** Cliente (RVIE) o proveedor (RCE) del comprobante: tipo de documento (Tabla 2), número y nombre. */
final readonly class SireParty
{
    public function __construct(
        public string $docType,
        public string $docNumber,
        public string $name,
    ) {}

    /**
     * Deriva el tipo del número: 11 dígitos RUC (6), 8 dígitos DNI (1), otro sin documento (0).
     * Sin documento se informa como «VARIOS» (consumidor o proveedor sin datos).
     */
    public static function fromStored(?string $document, ?string $name): self
    {
        $doc = trim((string) $document);
        $label = trim((string) $name) !== '' ? trim((string) $name) : 'VARIOS';

        return match (true) {
            $doc === '' => new self('0', '00000000', $label),
            strlen($doc) === 11 && ctype_digit($doc) => new self('6', $doc, $label),
            strlen($doc) === 8 && ctype_digit($doc) => new self('1', $doc, $label),
            default => new self('0', $doc, $label),
        };
    }
}
