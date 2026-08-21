<?php

declare(strict_types=1);

namespace App\Reporting\Ple;

/**
 * Agrega la vista previa de todos los libros PLE disponibles para un periodo.
 *
 * Incluye ventas (14.1), compras (8.1), diario (5.1) y mayor (6.1).
 */
final readonly class PlePreviewService
{
    public function __construct(
        private SalesLedgerExporter $salesExporter,
        private PurchaseLedgerExporter $purchaseExporter,
        private AccountingBookExporter $accountingExporter,
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
                'diary' => $this->accountingExporter->previewDiary($period),
                'ledger' => $this->accountingExporter->previewMayor($period),
            ],
        ];
    }
}
