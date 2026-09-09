<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

use App\Billing\Receipt\Service\VoucherPdfRenderer;
use App\Billing\Sunat\Domain\FileResponse;

readonly class ReceiptPdfUseCase
{
    public function __construct(
        private VoucherPdfRenderer $pdfRenderer,
    ) {}

    public function __invoke(ReceiptRequest $receiptRequest): FileResponse
    {
        return $this->pdfRenderer->render($receiptRequest->id);
    }
}
