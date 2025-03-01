<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;

readonly class InvoiceSendUseCase
{
    public function __construct(
        private GetOneInvoiceBySaleIdUseCase $getOneInvoiceUseCase,
        private BillSendUseCase $billSendUseCase,
        private UpdateInvoiceUseCase $updateInvoiceUseCase,
        private FileManager $fileManager,
        private SettingsService $settings,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws ServiceException
     * @throws Exception
     */
    public function __invoke(string $saleId): DocumentResponse
    {
        $invoice = $this->getOneInvoiceUseCase->__invoke($saleId);

        if (null == $invoice) {
            throw new ServiceException('Invoice not found');
        }

        $response = $this->billSendUseCase->__invoke($saleId);
        $invoice->digest = $response->hash;
        $this->fileManager->write(
            $this->settings->get(Constants::COMPANY_ID) . '/' . $response->name . '.xml',
            $response->xml
        );

        if ($response->sunatResponse->isSuccess()) {
            $invoice->envio_sunat = $response->sunatResponse->getCdrResponse()?->getCode();
            $invoice->mensaje_sunat = $response->sunatResponse->getCdrResponse()?->getDescription();
            $cdr = base64_decode($response->sunatResponse->getCdrZip() ?? '');
            $this->fileManager->write(
                $this->settings->get(Constants::COMPANY_ID) . '/R-' . $response->name . '.zip',
                $cdr
            );
        } else {
            $invoice->envio_sunat = $response->sunatResponse->getError()?->getCode();
            $invoice->mensaje_sunat = $response->sunatResponse->getError()?->getMessage();
        }

        $this->updateInvoiceUseCase->__invoke($invoice);

        return $response;
    }
}
