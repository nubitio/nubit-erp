<?php

namespace App\Sale\Domain;

use App\Billing\Domain\BillCreateUseCase;
use App\Invoice\Domain\InvoiceRepositoryInterface;

class SaleInvoiceUseCase
{
    public function __construct(
        private readonly BillCreateUseCase          $billCreateUseCase,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId, string $localId)
    {
        $this->invoiceRepository->create($saleId, $localId);
        $response = $this->billCreateUseCase->__invoke($saleId);
        if ($response->sunatResponse->success) {
            file_put_contents($_ENV['EFACT_DIR'] . $response->name . '.xml', $response->xml);
            $cdr = base64_decode($response->sunatResponse->cdrZip);
            file_put_contents($_ENV['EFACT_DIR'] . 'R-' . $response->name . '.zip', $cdr);
        } else {
            throw new \Exception($response->sunatResponse->error->message);
        }
    }
}