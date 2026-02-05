<?php

declare(strict_types=1);

namespace App\EInvoicing\Receipt\Domain;

use App\EInvoicing\Billing\Domain\BillPdfUseCase;
use App\EInvoicing\Billing\Domain\FileResponse;
use App\EInvoicing\Billing\Domain\NotePdfUseCase;
use App\Core\Exception\ServiceException;

readonly class ReceiptPdfUseCase
{
    public function __construct(
        private BillPdfUseCase $billPdfUseCase,
        private NotePdfUseCase $notePdfUseCase,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return match ($receiptRequest->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->billPdfUseCase->__invoke($receiptRequest->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->notePdfUseCase->__invoke($receiptRequest->id),
        };
    }
}
