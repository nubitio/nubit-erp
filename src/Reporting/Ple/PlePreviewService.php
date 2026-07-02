<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

/**
 * Agrega la vista previa de todos los libros PLE disponibles para un periodo.
 */
final readonly class PlePreviewService
{
    public function __construct(
        private SalesLedgerExporter $salesExporter,
        private PurchaseLedgerExporter $purchaseExporter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(string $period): array
    {
        return [
            'period' => $period,
            'generatedAt' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
            'books' => [
                'sales' => $this->salesExporter->preview($period),
                'purchases' => $this->purchaseExporter->preview($period),
                'diary' => $this->unavailableBook('diary', 'Libro Diario', 'LE05.2'),
                'ledger' => $this->unavailableBook('ledger', 'Libro Mayor', 'LE06.1'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableBook(string $key, string $label, string $pleCode): array
    {
        return [
            'key' => $key,
            'pleCode' => $pleCode,
            'label' => $label,
            'bookCode' => null,
            'filename' => null,
            'recordCount' => 0,
            'sizeBytes' => 0,
            'status' => 'unavailable',
            'statusLabel' => 'Sin datos aún',
            'rows' => [],
            'totals' => [
                'taxable' => 0.0,
                'igv' => 0.0,
                'total' => 0.0,
            ],
        ];
    }
}
