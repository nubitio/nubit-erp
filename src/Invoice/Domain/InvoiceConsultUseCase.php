<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillConsultUseCase;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
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
        private CompanyContext $companyContext,
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
                ['sale' => $saleId]
            );

            if ($consultResponse->getCdrResponse()?->isAccepted()) {
                $invoice->setSunatStatus($consultResponse->getCdrResponse()->getCode());
                $invoice->setSunatMessage($consultResponse->getCdrResponse()->getDescription());
                $cdr = base64_decode($consultResponse->getCdrZip() ?: '');
                $companyId = $this->companyContext->getId();
                $name = $companyId . $invoice->getVoucherTypeId() . $consultResponse->getCdrResponse()->getId() . '.zip';
                $this->fileManager->write(
                    $companyId . '/R-' . $name . '.zip',
                    $cdr
                );
            } else {
                $invoice->setSunatStatus($consultResponse->getError()?->getCode());
                $invoice->setSunatMessage($consultResponse->getError()?->getMessage());
            }

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();
        }

        return $consultResponse;
    }
}
