<?php

namespace App\Sale\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\Invoice\Domain\InvoiceRepositoryInterface;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private GetOneSaleUseCase          $getOneSaleUseCase,
        private UpdateSaleUseCase          $updateSaleUseCase,
        private BillSendUseCase            $billSendUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private FileManager                $fileManager,
        private SettingsService            $settings
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId): void
    {
        $sale = $this->getOneSaleUseCase->__invoke($saleId);
        if ($sale->has_invoice) {
            return;
        }
        $this->invoiceRepository->create($sale);
        $invoice = $this->invoiceRepository->getOne($saleId);
        $sale->has_invoice = true;
        $sale->invoice = $invoice->serie . '-' . $invoice->numero;
        $sale->total = $invoice->total;
        $this->updateSaleUseCase->__invoke($sale);
        $response = $this->billSendUseCase->__invoke($saleId);
        $invoice->digest = $response->hash;
        $this->fileManager->write($this->settings->get(Constants::COMPANY_ID) . '/' . $response->name . '.xml', $response->xml);
        if ($response->sunatResponse->success) {
            $invoice->envio_sunat = $response->sunatResponse->cdrResponse->code;
            $invoice->mensaje_sunat = $response->sunatResponse->cdrResponse->description;
            $cdr = base64_decode($response->sunatResponse->cdrZip);
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID) . '/' . 'R-' . $response->name . '.zip', $cdr);
            $this->invoiceRepository->update($invoice);
        } else {
            $invoice->envio_sunat = $response->sunatResponse->error->code;
            $invoice->mensaje_sunat = $response->sunatResponse->error->message;
            $this->invoiceRepository->update($invoice);
            throw new \Exception($response->sunatResponse->error->message);
        }
    }
}
