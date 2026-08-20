<?php

declare(strict_types=1);

namespace App\Billing\Sale;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sale\Void\CashRefundRegistrar;
use App\Billing\Sale\Void\StockVoidReverserInterface;
use App\Billing\Sale\Void\SunatVoidService;
use App\Billing\Sale\Void\TaxDocumentVoidUpdater;
use App\Billing\Service\SaleV2Loader;
use App\Billing\Shared\ErrorMessages;
use App\Efact\Audit\AuditLogger;
use App\Sales\Domain\DocType;
use App\Sales\Domain\SaleException;
use App\Sales\Entity\SaleRecord;
use App\Sales\State\SalesWriteGateway;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Http\ApiResponse;

readonly class SaleVoidUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaxDocumentRepository $taxDocumentRepository,
        private SunatVoidService $sunatVoidService,
        private TaxDocumentVoidUpdater $taxDocumentVoidUpdater,
        private CashRefundRegistrar $cashRefundRegistrar,
        private AuditLogger $auditLogger,
        private SalesWriteGateway $salesWriteGateway,
        private SaleV2Loader $saleV2Loader,
        private StockVoidReverserInterface $stockVoidReverser,
    ) {}

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \DateMalformedStringException
     * @throws \Throwable
     * @throws MathException
     * @throws Exception
     */
    public function __invoke(string $saleId, string $reason, int $userId, bool $restoreStock = false): ApiResponse
    {
        // Load sale from V2 tables (v2_authoritative mode; no ORM movimiento fallback).
        $sale = $this->saleV2Loader->loadAsSale($saleId);
        if (!$sale instanceof SaleRecord) {
            throw new ServiceException(ErrorMessages::SALE_NOT_EXISTS);
        }

        if ($sale->getStatus() === 'VOIDED') {
            throw SaleException::alreadyVoided();
        }

        $lima = new \DateTimeZone('America/Lima');
        $rawFecha = $sale->getFecha();
        $saleDateLima = $rawFecha !== null
            ? \DateTimeImmutable::createFromInterface($rawFecha)->setTimezone($lima)->format('Y-m-d')
            : null;
        $limitDateLima = new \DateTimeImmutable('now', $lima)
            ->modify('-7 days')
            ->format('Y-m-d');
        if ($saleDateLima < $limitDateLima) {
            throw SaleException::tooOld();
        }

        if ($this->taxDocumentRepository->hasCreditNoteForSale($saleId)) {
            throw SaleException::hasCreditNote();
        }

        // Resolve document type without loading the full voucher DTO,
        // so this works in v2_authoritative mode where no movimiento row exists.
        $documentType = $this->taxDocumentRepository->findDocumentTypeBySaleId($saleId) ?? DocType::NOTA_VENTA->value;

        if ($documentType === DocType::NOTA_VENTA->value) {
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            try {
                $sale->setGlosa($reason);
                $sale->setStatus('VOIDED');
                $sale->setUpdatedBy($userId);
                $sale->setDateUpdated(new \DateTimeImmutable());
                $this->cashRefundRegistrar->register($sale, $userId);
                $this->syncVoidToSalesV2($sale, $reason);

                if ($restoreStock) {
                    $this->stockVoidReverser->reverse($sale);
                }
                $this->entityManager->flush();
                $connection->commit();
            } catch (\Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }

                throw $exception;
            }

            $this->auditLogger->log('sale.void.result', [
                'sale_id' => $saleId,
                'document_type' => $documentType,
                'success' => true,
                'message' => ErrorMessages::VOID_SUCCESSFUL,
                'restore_stock' => $restoreStock,
            ]);

            return new ApiResponse(true, ErrorMessages::VOID_SUCCESSFUL);
        }
        if (!in_array($documentType, [DocType::FACTURA->value, DocType::BOLETA->value], true)) {
            throw new ServiceException(ErrorMessages::UNSUPPORTED_VOUCHER_TYPE_FOR_VOID);
        }

        $this->auditLogger->log('sale.void.requested', [
            'sale_id' => $saleId,
            'reason' => $reason,
            'user_id' => $userId,
            'document_type' => $documentType,
        ]);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $sunatResponse = $this->sunatVoidService->void($documentType, $saleId, $reason);

            // Find the accepted document that was just voided (latest accepted wins;
            // may be a replacement if the original was rejected).
            $taxDocument = $this->taxDocumentRepository->findLatestAcceptedBySaleAndType($saleId, $documentType);
            if ($taxDocument === null) {
                throw new ServiceException(ErrorMessages::TAX_DOCUMENT_NOT_FOUND);
            }

            $voidedStatus = $sunatResponse->success ? 2 : 3;
            $this->taxDocumentVoidUpdater->applyVoidResult($taxDocument, $sunatResponse, $voidedStatus);

            $sale->setGlosa($reason);
            $sale->setStatus('VOIDED');
            $sale->setUpdatedBy($userId);
            $sale->setDateUpdated(new \DateTimeImmutable());

            $this->cashRefundRegistrar->register($sale, $userId);

            $this->entityManager->flush();
            $this->syncVoidToSalesV2($sale, $reason);

            if ($restoreStock) {
                $this->stockVoidReverser->reverse($sale);
            }

            $connection->commit();

            $message = $sunatResponse->success
                ? ErrorMessages::VOID_SUCCESSFUL
                : $sunatResponse->message ?? ErrorMessages::VOID_ERROR;

            $this->auditLogger->log('sale.void.result', [
                'sale_id' => $saleId,
                'document_type' => $documentType,
                'success' => $sunatResponse->success,
                'message' => $message,
            ]);

            return new ApiResponse($sunatResponse->success, $message);
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            $this->auditLogger->log('sale.void.result', [
                'sale_id' => $saleId,
                'document_type' => $documentType,
                'success' => false,
                'error' => $throwable->getMessage(),
            ]);
            throw $throwable;
        }
    }

    private function syncVoidToSalesV2(SaleRecord $sale, string $reason): void
    {
        try {
            $this->salesWriteGateway->markVoided($sale, $reason);
        } catch (\Throwable $exception) {
            $this->auditLogger->log('sale.void.sales_v2_sync_failed', [
                'sale_id' => $sale->getId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
