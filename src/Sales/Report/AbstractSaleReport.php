<?php

declare(strict_types=1);

namespace App\Sales\Report;

use App\Settings\Service\SunatStateService;
use Nubit\Platform\Report\ExportableReportInterface;

/**
 * Base class for sale reports that may need a SUNAT receipt filter.
 *
 * Reports that do NOT query the `sales` table directly (e.g. AccountingReport,
 * CreditNotesReport) can implement ExportableReportInterface directly instead.
 */
abstract class AbstractSaleReport implements ExportableReportInterface
{
    public function __construct(
        protected readonly SunatStateService $sunatState,
    ) {}

    /**
     * Returns an additional AND clause that restricts to sales with a
     * comprobante (01/03) when the SUNAT receipt feature is active.
     * Returns '' otherwise, so it interpolates cleanly into SQL.
     */
    protected function receiptFilter(string $salesAlias = 's'): string
    {
        if ($this->sunatState->isReceiptRequired()) {
            return (
                'AND EXISTS (SELECT 1 FROM tax_document td '
                . "WHERE (td.movimiento_id = {$salesAlias}.legacy_movimiento_id OR td.sale_id = {$salesAlias}.id) "
                . "AND td.document_type IN ('01','03'))"
            );
        }

        return '';
    }
}
