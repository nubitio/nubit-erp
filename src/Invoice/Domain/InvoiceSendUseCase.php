<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Service\FileManager;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Invoice;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;

readonly class InvoiceSendUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BillSendUseCase $billSendUseCase,
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
        $invoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(
            ['movimiento_id' => $saleId]
        );

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

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }
}
