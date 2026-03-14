<?php

declare(strict_types=1);

namespace App\EInvoicing\Sale;

use App\Core\Audit\AuditLogger;
use App\Core\Exception\ServiceException;
use App\Core\Http\ApiResponse;
use App\EInvoicing\Sale\Void\CashRefundRegistrar;
use App\EInvoicing\Sale\Void\SunatVoidService;
use App\EInvoicing\Sale\Void\TaxDocumentVoidUpdater;
use App\EInvoicing\Service\SaleV2Loader;
use App\EInvoicing\Shared\ErrorMessages;
use App\Entity\SaleRecord;
use App\Repository\TaxDocumentRepository;
use App\Sale\Domain\DocType;
use App\Sale\Domain\SaleException;
use App\State\Sale\SalesWriteGateway;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

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
    ) {
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws \DateMalformedStringException
     * @throws \Throwable
     * @throws MathException
     * @throws Exception
     */
    public function __invoke(
        string $saleId,
        string $reason,
        int $userId,
    ): ApiResponse {
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
        $limitDateLima = (new \DateTimeImmutable('now', $lima))->modify('-7 days')->format('Y-m-d');
        if ($saleDateLima < $limitDateLima) {
            throw SaleException::tooOld();
        }

        if ($this->taxDocumentRepository->hasCreditNoteForSale($saleId)) {
            throw SaleException::hasCreditNote();
        }

        // Resolve document type without loading the full voucher DTO,
        // so this works in v2_authoritative mode where no movimiento row exists.
        $documentType = $this->taxDocumentRepository->findDocumentTypeBySaleId($saleId)
            ?? DocType::NOTA_VENTA->value;

        if ($documentType === DocType::NOTA_VENTA->value) {
            $sale->setGlosa($reason);
            $sale->setStatus('VOIDED');
            $sale->setUpdatedBy($userId);
            $sale->setDateUpdated(new \DateTimeImmutable());
            $this->cashRefundRegistrar->register($sale, $userId);
            $this->entityManager->flush();
            $this->syncVoidToSalesV2($sale, $reason);

            $this->auditLogger->log('sale.void.result', [
                'sale_id' => $saleId,
                'document_type' => $documentType,
                'success' => true,
                'message' => ErrorMessages::VOID_SUCCESSFUL,
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

            // Search tax_document by sale_id (V2 — v2_authoritative mode).
            $taxDocument = $this->taxDocumentRepository->findOneBy([
                'saleRecord' => $saleId,
                'documentType' => $documentType,
            ]);
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
            $connection->commit();

            $message = $sunatResponse->success
                ? ErrorMessages::VOID_SUCCESSFUL
                : ($sunatResponse->message ?? ErrorMessages::VOID_ERROR);

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
