<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use Doctrine\DBAL\Exception;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;

readonly class InvoiceConsultUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private BillConsultUseCase $billConsultUseCase,
        private FileManager $fileManager,
        private SettingsService $settings
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function __invoke(string $saleId): StatusCdrResult
    {
        $consultResponse = $this->billConsultUseCase->__invoke($saleId);

        if ($consultResponse->isSuccess()) {
            $invoice = $this->invoiceRepository->getOneBySaleId($saleId);

            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $invoice->envio_sunat = $consultResponse->getCdrResponse()->getCode();
                $invoice->mensaje_sunat = $consultResponse->getCdrResponse()->getDescription();
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $name = $this->settings->get(Constants::COMPANY_ID).$invoice->tipo_comprobante_id.$consultResponse->getCdrResponse()->getId().'.zip';
                $this->fileManager->write($this->settings->get(Constants::COMPANY_ID).'/R-'.$name.'.zip', $cdr);
            } else {
                $invoice->envio_sunat = $consultResponse->getError()?->getCode();
                $invoice->mensaje_sunat = $consultResponse->getError()?->getMessage();
            }

            $this->invoiceRepository->update($invoice);
        }

        return $consultResponse;
    }
}
