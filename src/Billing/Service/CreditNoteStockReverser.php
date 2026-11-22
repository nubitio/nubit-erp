<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Sale\Void\StockVoidReverserInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use Psr\Log\LoggerInterface;

/**
 * Reverses or restores inventory when a credit note is created.
 *
 * Two strategies depending on the SUNAT reason code:
 *
 *   FULL VOID (codes 01, 02, 06 — already handled by voidSaleIfCreditNoteAnnuls)
 *     → delegates to StockVoidReverserInterface, which generates VOID_REVERSAL
 *       movements identical to a Comunicación de Baja.
 *
 *   PARTIAL RETURN (code 07 only)
 *     → generates one RETURN_IN movement per credit note line, using the
 *       line quantity and the warehouse of the original sale.
 *       Only affects TRACKED products (productId must be set on the line).
 *
 * Both paths are idempotent via InventoryService's idempotency_key.
 */
final readonly class CreditNoteStockReverser implements CreditNoteStockReverserInterface
{
    /** Reason codes that represent a full cancellation — stock reversed via VOID_REVERSAL. */
    private const FULL_VOID_CODES = ['01', '02', '06'];

    private const string PARTIAL_RETURN_CODE = '07';

    public function __construct(
        private InventoryService $inventoryService,
        private StockVoidReverserInterface $stockVoidReverser,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function reverse(TaxDocument $creditNote): void
    {
        $reasonCode = $creditNote->getReasonCode();
        $saleRecord = $creditNote->getSaleRecord();

        if ($saleRecord === null) {
            $this->logger->info('CreditNoteStockReverser: no sale record, skipping', [
                'credit_note_id' => $creditNote->getId(),
            ]);

            return;
        }

        if (in_array($reasonCode, self::FULL_VOID_CODES, true)) {
            // Full cancellation — reuse the sale void reversal (finds SALE_OUT by sale_id)
            $this->stockVoidReverser->reverse($saleRecord);

            return;
        }

        if ($reasonCode !== self::PARTIAL_RETURN_CODE) {
            $this->logger->info('CreditNoteStockReverser: reason does not represent a physical return', [
                'credit_note_id' => $creditNote->getId(),
                'reason_code' => $reasonCode,
            ]);

            return;
        }

        // Partial return — generate RETURN_IN per line
        $warehouse = $saleRecord->getWarehouse();
        if ($warehouse === null) {
            $this->logger->info('CreditNoteStockReverser: sale has no warehouse, skipping partial return', [
                'credit_note_id' => $creditNote->getId(),
            ]);

            return;
        }

        $creditNoteId = (string) $creditNote->getId();
        $warehouseId = (string) $warehouse->getId();
        $lineNo = 0;
        $reversed = 0;

        foreach ($creditNote->getLines() as $line) {
            /** @var TaxDocumentLine $line */
            ++$lineNo;

            if (!$line->isRestock()) {
                continue; // the user kept these goods out of the warehouse (damaged, discarded)
            }

            $productId = $line->getProductId();
            if ($productId === null) {
                continue; // service line or non-inventory line
            }

            $qty = (float) $line->getQuantity();
            if ($qty <= 0.0) {
                continue;
            }

            $qtyString = number_format($qty, 6, '.', '');

            try {
                $this->inventoryService->recordMovement(
                    new MovementDraft(
                        warehouseId: $warehouseId,
                        productId: $productId,
                        movementType: StockMovementType::RETURN_IN,
                        qtyDeltaBase: $qtyString,
                        unitId: 0,
                        qtyOriginal: $qtyString,
                        sourceType: 'CREDIT_NOTE',
                        sourceId: sprintf('%s:%d', $creditNoteId, $lineNo),
                        note: sprintf(
                            'Devolución cliente — nota de crédito %s (código: %s)',
                            $creditNote->getFullNumber(),
                            $reasonCode,
                        ),
                    ),
                    behavior: InventoryBehavior::TRACKED,
                );
                ++$reversed;
            } catch (\Throwable $e) {
                $this->logger->error('CreditNoteStockReverser: failed to record return movement', [
                    'credit_note_id' => $creditNoteId,
                    'line_no' => $lineNo,
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->logger->info('CreditNoteStockReverser: partial return processed', [
            'credit_note_id' => $creditNoteId,
            'reason_code' => $reasonCode,
            'lines_reversed' => $reversed,
        ]);
    }
}
