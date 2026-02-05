<?php

declare(strict_types=1);

namespace App\Sale\Domain;

use App\Billing\Domain\SummaryCreateUseCase;
use App\Billing\Domain\VoidedCreateUseCase;
use App\Billing\Model\Response\SummaryResponse;
use App\Core\Exception\ServiceException;
use App\Core\Filesystem\FileManager;
use App\Core\Http\ApiResponse;
use App\Core\Service\CompanyContext;
use App\Entity\CashMovement;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\PaymentMethod;
use App\Entity\Sale;
use App\Enum\CashShiftStatus;
use App\Repository\CashMovementCategoryRepository;
use App\Repository\TaxDocumentRepository;
use App\Service\TaxDocumentSyncService;
use Brick\Math\Exception\MathException;
use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class SaleVoidUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaxDocumentRepository $taxDocumentRepository,
        private VoidedCreateUseCase $voidedCreateUseCase,
        private SummaryCreateUseCase $summaryCreateUseCase,
        private FileManager $fileManager,
        private CompanyContext $companyContext,
        private LoggerInterface $logger,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private TaxDocumentSyncService $taxDocumentSyncService,
        private Connection $connection,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     * @throws FilesystemException
     * @throws MathException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function __invoke(
        string $saleId,
        string $reason,
        int $userId,
    ): ApiResponse {
        $voucher = $this->taxDocumentRepository->getVoucher($saleId);
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
        $sale->setDateUpdated(new DateTime());

        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        $this->registerCashRefund($sale, $userId);

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
     * @throws DateMalformedStringException
     * @throws ExceptionInterface
     */
    private function voidInvoice(
        string $saleId,
        string $reason,
    ): SummaryResponse {
        $invoice = $this->getInvoiceTaxDocument($saleId);

        if (($invoice['sunat_status'] ?? null) !== '0') {
            throw SaleException::notIssued();
        }

        $response = $this->voidedCreateUseCase->__invoke($saleId, $reason);
        $voidCorrelation = explode('-', $response->name)[3] ?? null;
        $voidTicket = $response->sunatResponse->getTicket();
        $voidedStatus = $response->sunatResponse->isSuccess() ? 2 : 3;

        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                $this->companyContext->getId() . '/' . $response->name
                . '.xml',
                $response->xml
            );
        }

        $this->connection->update('tax_document', [
            'void_correlation' => $voidCorrelation,
            'void_ticket' => $voidTicket ?: ($invoice['void_ticket'] ?? null),
            'updated_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ], [
            'tax_document_id' => $invoice['tax_document_id'],
        ]);

        $this->taxDocumentSyncService->recordSunatEvent(
            documentType: strval($invoice['document_type']),
            series: strval($invoice['series']),
            number: strval($invoice['number']),
            eventType: 'sunat_void',
            statusCode: $voidTicket,
            statusMessage: $voidedStatus === 2 ? 'Solicitud de anulación aceptada' : 'Solicitud de anulación pendiente/error',
            digest: $invoice['digest'] ?? null,
            payload: ['voided' => $voidedStatus],
        );

        return $response;
    }

    /**
     * @throws FilesystemException
     * @throws SaleException
     * @throws \Exception
     * @throws Exception
     * @throws ExceptionInterface
     */
    private function voidReceipt(string $saleId): SummaryResponse
    {
        $invoice = $this->getInvoiceTaxDocument($saleId);

        if (($invoice['sunat_status'] ?? null) !== '0') {
            throw SaleException::notIssued();
        }

        $response = $this->summaryCreateUseCase->__invoke($saleId);
        $voidCorrelation = explode('-', $response->name)[3] ?? null;
        if ($response->sunatResponse->isSuccess()) {
            $this->fileManager->write(
                $this->companyContext->getId() . '/' . $response->name
                . '.xml',
                $response->xml
            );
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

        $this->connection->update('tax_document', [
            'void_correlation' => $voidCorrelation,
            'void_ticket' => $response->sunatResponse->getTicket(),
            'updated_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ], [
            'tax_document_id' => $invoice['tax_document_id'],
        ]);

        $this->taxDocumentSyncService->recordSunatEvent(
            documentType: strval($invoice['document_type']),
            series: strval($invoice['series']),
            number: strval($invoice['number']),
            eventType: 'sunat_void',
            statusCode: $response->sunatResponse->getTicket(),
            statusMessage: 'Solicitud de anulación aceptada',
            digest: $invoice['digest'] ?? null,
            payload: ['voided' => 2],
        );

        return $response;
    }

    /**
     * @return array<string, mixed>
     * @throws SaleException
     * @throws Exception
     */
    private function getInvoiceTaxDocument(string $saleId): array
    {
        $invoice = $this->connection->fetchAssociative(
            "SELECT tax_document_id, document_type, series, number, sunat_status, void_ticket, digest
             FROM tax_document
             WHERE movimiento_id = :saleId
               AND document_type IN ('01', '03')
             ORDER BY created_at DESC
             LIMIT 1",
            ['saleId' => $saleId]
        );

        if ($invoice === false) {
            throw SaleException::notIssued();
        }

        return $invoice;
    }

    private function registerCashRefund(Sale $sale, int $userId): void
    {
        if ($sale->getTipoPago()?->getId() !== PaymentMethod::CASH) {
            return;
        }

        $cashShift = $this->entityManager->getRepository(CashShift::class)
            ->findOneBy([
                'status' => CashShiftStatus::OPEN,
                'openedBy' => $userId,
            ]);

        if (!$cashShift instanceof CashShift) {
            $this->logger->warning('No open cash shift for cash refund movement', [
                'sale_id' => $sale->getId(),
                'user_id' => $userId,
            ]);

            return;
        }

        $category = $this->cashMovementCategoryRepository->findOneBy([
            'code' => CashMovementCategory::CUSTOMER_REFUND,
        ]);

        if (!$category instanceof CashMovementCategory) {
            $this->logger->error('Cash refund category not found', [
                'sale_id' => $sale->getId(),
            ]);

            return;
        }

        $movement = new CashMovement();
        $movement->setCashShift($cashShift);
        $movement->setCategory($category);
        $movement->setType($category->getType());
        $movement->setAmount($sale->getTotal() ?? 0);
        $movement->setDescription('Anulación de venta N° ' . $sale->getserie() . '-' . $sale->getNumero());
        $movement->setCreatedAt(new DateTimeImmutable());

        $this->entityManager->persist($movement);
        $this->entityManager->flush();
    }
}
