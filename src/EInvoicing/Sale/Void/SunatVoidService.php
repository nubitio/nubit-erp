<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale\Void;

use Nubit\Platform\Exception\ServiceException;

final readonly class SunatVoidService
{
    /**
     * @var VoidStrategyInterface[]
     */
    private array $strategies;

    public function __construct(
        InvoiceVoidStrategy $invoiceStrategy,
        ReceiptVoidStrategy $receiptStrategy,
    ) {
        $this->strategies = [$invoiceStrategy, $receiptStrategy];
    }

    public function void(string $documentType, string $saleId, string $reason): SunatVoidResponse
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($documentType)) {
                return $strategy->void($saleId, $reason);
            }
        }

        throw new ServiceException('Tipo de comprobante no soportado para anulación.');
    }
}
