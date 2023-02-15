<?php

namespace App\Sale\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Invoice\Domain\InvoiceRepositoryInterface;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private GetOneSaleUseCase          $getOneSaleUseCase,
        private UpdateSaleUseCase          $updateSaleUseCase,
        private BillSendUseCase            $billSendUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId, string $localId): void
    {
        $sale = $this->getOneSaleUseCase->__invoke($saleId);
        if ($sale->has_invoice) {
            return;
        }
        $this->invoiceRepository->create($sale, $localId);
        $invoice = $this->invoiceRepository->getOne($saleId);
        $response = $this->billSendUseCase->__invoke($saleId);
        $invoice->digest = $response->hash;
        file_put_contents($_ENV['EFACT_DIR'] . $response->name . '.xml', $response->xml);
        if ($response->sunatResponse->success) {
            $invoice->envio_sunat = $response->sunatResponse->cdrResponse->code;
            $invoice->mensaje_sunat = $response->sunatResponse->cdrResponse->description;
            $cdr = base64_decode($response->sunatResponse->cdrZip);
            file_put_contents($_ENV['EFACT_DIR'] . 'R-' . $response->name . '.zip', $cdr);
            $this->invoiceRepository->update($invoice);
            $sale->has_invoice = true;
            $sale->invoice = $invoice->serie . '-' . $invoice->numero;
            $this->updateSaleUseCase->__invoke($sale);
        } else {
            $invoice->envio_sunat = $response->sunatResponse->error->code;
            $invoice->mensaje_sunat = $response->sunatResponse->error->message;
            $this->invoiceRepository->update($invoice);
            throw new \Exception($response->sunatResponse->error->message);
        }
    }
}
