<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Receipt\Service\VoucherPdfRenderer;

readonly class BillPdfUseCase
{
    public function __construct(
        private VoucherPdfRenderer $pdfRenderer,
    ) {}

    public function __invoke(string $taxDocumentId): FileResponse
    {
        return $this->pdfRenderer->render($taxDocumentId);
    }
}
