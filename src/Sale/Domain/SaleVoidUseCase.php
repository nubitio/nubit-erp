<?php

namespace App\Sale\Domain;

use App\Billing\Domain\SummaryCreateUseCase;
use App\Billing\Domain\VoidedCreateUseCase;
use App\Billing\Model\Response\SummaryResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Http\ApiResponse;
use App\Core\Service\CompanyContext;
use App\Entity\Sale;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class SaleVoidUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepository $invoiceRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws Exception
     * @throws \DateMalformedStringException
     * @throws ExceptionInterface
     */
    public function __invoke(
        string $saleId,
        string $reason,
        int $userId,
    ): ApiResponse {
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $sale = $this->entityManager->find(Sale::class, $saleId);

        if (2 === $sale->getEstado()) {
            throw SaleException::alreadyVoided();
        }

        if (
            $sale->getFecha()?->format('Y-m-d') < date(
                'Y-m-d',
                strtotime('-7 days')
            )
        ) {
            throw SaleException::tooOld();
        }

        $response = null;

        if ($voucher->voucher_type === DocType::FACTURA->value) {
            $response = $this->voidInvoice($saleId, $reason);
        } elseif ($voucher->voucher_type === DocType::BOLETA->value) {
            $response = $this->voidReceipt($saleId);
        }

        $sale->setGlosa($reason);
        $sale->setEstado(2);
        $sale->setUpdatedBy($userId);
        $sale->setDateUpdated(new \DateTime());

        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        if ($response instanceof SummaryResponse) {
            $sunatResponse = $response->sunatResponse;
            $success = $sunatResponse->isSuccess();
            $description = $success ? 'Anulado correctamente'
                : $sunatResponse->getError()?->getMessage();

            return new ApiResponse(
                $success ?? false,
                $description ?? 'Error al anular'
            );
        }

        return new ApiResponse(
            true,
            'Anulado correctamente'
        );
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws Exception
     * @throws \DateMalformedStringException
     */
    private function voidInvoice(
        string $saleId,
        string $reason,
    ): SummaryResponse {
        $invoice = $this->invoiceRepository->findOneBy(
            ['sale' => $saleId]
        );

        if ('0' !== $invoice->getSunatStatus()) {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
        $invoice->setVoidCorrelation(explode('-', $response->name)[3]);
        $invoice->setUpdatedAt(new \DateTime());
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                $this->companyContext->getId() . '/' . $response->name
                . '.xml',
                $response->xml
            );
            $invoice->setVoidTicket($response->sunatResponse->getTicket());
            $invoice->setVoided(2);
        } else {
            $invoice->setVoidTicket(
                $response->sunatResponse->getTicket() ??
                $invoice->getVoidTicket()
            );
            $invoice->setVoided(3);
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws \Exception
     */
    private function voidReceipt(string $saleId): SummaryResponse
    {
        $invoice = $this->invoiceRepository->findOneBy(
            ['sale' => $saleId]
        );

        if ('0' !== $invoice->getSunatStatus()) {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke($saleId);
        $invoice->setVoidCorrelation(explode('-', $response->name)[3]);
        $invoice->setUpdatedAt(new \DateTime());
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                $this->companyContext->getId() . '/' . $response->name
                . '.xml',
                $response->xml
            );
            $invoice->setVoidTicket($response->sunatResponse->getTicket());
            $invoice->setVoided(2);
        } else {
            $this->logger->error('Error al anular el comprobante', [
                'response' => $response,
                'sale_id' => $saleId,
                'message' => $response->sunatResponse->getError()?->getMessage() ?? 'Error desconocido',
            ]);
            throw new ServiceException(
                'Error al anular el comprobante' . $response->sunatResponse->getError()?->getMessage()
            );
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $response;
    }
}
