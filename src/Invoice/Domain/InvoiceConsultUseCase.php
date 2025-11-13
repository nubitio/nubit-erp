<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Filesystem\FileManager;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Greenter\Model\Response\StatusCdrResult;
use League\Flysystem\FilesystemException;

readonly class InvoiceConsultUseCase
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BillConsultUseCase $billConsultUseCase,
        private FileManager $fileManager,
        private SettingsService $settings,
        private EntityManagerInterface $entityManager,
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
            $invoice = $this->invoiceRepository->findOneBy(
                ['movimiento_id' => $saleId]
            );

            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $invoice->envio_sunat = $consultResponse->getCdrResponse()->getCode();
                $invoice->mensaje_sunat = $consultResponse->getCdrResponse()->getDescription();
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $name = $this->settings->get(
                        Constants::COMPANY_ID
                    ) . $invoice->tipo_comprobante_id . $consultResponse->getCdrResponse()->getId() . '.zip';
                $this->fileManager->write(
                    $this->settings->get(Constants::COMPANY_ID) . '/R-' . $name . '.zip',
                    $cdr
                );
            } else {
                $invoice->envio_sunat = $consultResponse->getError()?->getCode();
                $invoice->mensaje_sunat = $consultResponse->getError()?->getMessage();
            }

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();
        }

        return $consultResponse;
    }
}
