<?php

namespace App\Sale\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\TenantAwareSettingsService;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use League\Flysystem\FilesystemException;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private GetOneSaleUseCase $getOneSaleUseCase,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private UpdateSaleUseCase $updateSaleUseCase,
        private BillSendUseCase $billSendUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private FileManager $fileManager,
        private TenantAwareSettingsService $settings
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException
     */
    public function __invoke(string $saleId): ?Voucher
    {
        $sale = $this->getOneSaleUseCase->__invoke($saleId);

        if (!$sale->has_invoice) {
            $this->invoiceRepository->create($sale);
        }

        $invoice = $this->invoiceRepository->getOne($saleId);

        $sale->has_invoice = true;
        $sale->invoice = $invoice->serie.'-'.$invoice->numero;
        $sale->total = $invoice->total;
        $this->updateSaleUseCase->__invoke($sale);

        if (is_numeric($invoice->envio_sunat)) {
            return $this->getOneVoucherUseCase->__invoke($saleId);
        }

        $response = $this->billSendUseCase->__invoke($saleId);
        $invoice->digest = $response->hash;
        $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/'.$response->name.'.xml', $response->xml);

        if ($response->sunatResponse->success) {
            $invoice->envio_sunat = $response->sunatResponse->cdrResponse->code;
            $invoice->mensaje_sunat = $response->sunatResponse->cdrResponse->description;
            $cdr = base64_decode($response->sunatResponse->cdrZip);
            $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/R-'.$response->name.'.zip', $cdr);
            $this->invoiceRepository->update($invoice);
        } else {
            $invoice->envio_sunat = $response->sunatResponse->error->code;
            $invoice->mensaje_sunat = $response->sunatResponse->error->message;
            $this->invoiceRepository->update($invoice);
            throw new ServiceException($response->sunatResponse->error->message);
        }

        return $this->getOneVoucherUseCase->__invoke($saleId);
    }
}
