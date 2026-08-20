<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use App\Purchasing\State\PurchaseVoidServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles DELETE /purchases/{id}.
 *
 * Voiding a goods receipt is a two-step operation:
 *   1. Reverse all PURCHASE_IN stock movements → generates PURCHASE_VOID entries
 *   2. Mark the purchase record as VOIDED + set deleted_at (soft-delete)
 *
 * This preserves the audit trail: the purchase row remains in the DB with
 * status=VOIDED and the ledger shows both the original PURCHASE_IN and its
 * PURCHASE_VOID reversal.
 *
 * Guard: a purchase that is already VOIDED cannot be voided again.
 *
 * @implements ProcessorInterface<Purchase, null>
 */
final readonly class PurchaseVoidProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private PurchaseVoidServiceInterface $purchaseVoidService,
        private PurchaseOrderReceiptService $purchaseOrderReceiptService,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof Purchase) {
            return null;
        }

        if ($data->getStatus() === 'VOIDED') {
            throw new UnprocessableEntityHttpException('Esta compra ya fue anulada.');
        }

        $connection = $this->entityManager->getConnection();
        $managesTransaction = !$connection->isTransactionActive();
        if ($managesTransaction) {
            $connection->beginTransaction();
        }

        try {
            // Reverse stock first — if it fails, the purchase record stays intact
            $this->purchaseVoidService->reverse($data);

            if ($data->getPurchaseOrder() !== null) {
                $this->purchaseOrderReceiptService->revertReceipt($data);
            }

            // Mark as voided
            $data->setStatus('VOIDED');
            $data->setDeletedAt(new \DateTime());

            $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            if ($managesTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($managesTransaction && $connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        return null;
    }
}
