<?php

declare(strict_types=1);

namespace App\Invoice\Domain;

use App\Billing\Domain\BillSendUseCase;
use App\Billing\Model\Response\DocumentResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Service\CompanyContext;
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
        private CompanyContext $companyContext,
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
            ['sale' => $saleId]
        );

        if (null == $invoice) {
            throw new ServiceException('Invoice not found');
        }

        $response = $this->billSendUseCase->__invoke($saleId);
        $invoice->setDigest($response->hash);
        $companyId = $this->companyContext->getId();
        $this->fileManager->write(
            $companyId . '/' . $response->name . '.xml',
            $response->xml
        );

        if ($response->sunatResponse->isSuccess()) {
            $invoice->setSunatStatus($response->sunatResponse->getCdrResponse()?->getCode());
            $invoice->setSunatMessage($response->sunatResponse->getCdrResponse()?->getDescription());
            $cdr = base64_decode($response->sunatResponse->getCdrZip() ?? '');
            $this->fileManager->write(
                $companyId . '/R-' . $response->name . '.zip',
                $cdr
            );
        } else {
            $invoice->setSunatStatus($response->sunatResponse->getError()?->getCode());
            $invoice->setSunatMessage($response->sunatResponse->getError()?->getMessage());
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }
}
