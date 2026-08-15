<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Catalog\Entity\Product;
use App\Efact\Domain\Money;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\Entity\PurchaseOrderItem;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;

/**
 * CRUD-adjacent business logic for PurchaseOrder: building items from the
 * writeItems payload, recomputing totals, and guarding status transitions.
 * Mirrors App\Sales\Service\SaleQuoteService's shape (Fase 2 precedent).
 */
readonly class PurchaseOrderService
{
    /**
     * partially_received/received are receipt-driven only (set exclusively
     * by PurchaseOrderReceiptService) and are deliberately absent from this
     * map — a manual Patch can never reach them.
     */
    private const array ALLOWED_TRANSITIONS = [
        PurchaseOrder::STATUS_DRAFT => [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_SENT => [PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_RECEIVED => [],
        PurchaseOrder::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $writeItems
     * @return list<PurchaseOrderItem>
     */
    public function buildItemsFromPayload(array $writeItems): array
    {
        $items = [];
        $lineNo = 1;

        foreach ($writeItems as $itemData) {
            $item = new PurchaseOrderItem();

            $productId = $this->extractProductId($itemData['product'] ?? null);
            $product = null;
            if ($productId !== null) {
                $product = $this->entityManager->find(Product::class, $productId);
                $item->setProduct($product);
            }

            $qty = (float) ($itemData['orderedQty'] ?? 1);
            $unitCost = $itemData['expectedUnitCost'] ?? '0.00';

            $grossAmount = Money::money(BigDecimal::of((string) $unitCost)->multipliedBy($qty));

            $item->setLineNo($lineNo++);
            $item->setOrderedQty(number_format($qty, 3, '.', ''));
            $item->setExpectedUnitCost(number_format((float) $unitCost, 4, '.', ''));
            $item->setGrossAmount(Money::toString($grossAmount));
            $item->setTaxAmount('0.00');
            $item->setTotalAmount(Money::toString($grossAmount));

            $description = trim((string) ($itemData['nameSnapshot'] ?? ''));
            if ($description !== '') {
                $item->setNameSnapshot(mb_substr($description, 0, 255));
            }

            if ($product !== null) {
                if ($item->getNameSnapshot() === '') {
                    $item->setNameSnapshot($product->getName());
                }
                $item->setSkuSnapshot($product->getCode());
                $item->setUomSnapshot($product->getUnitOfMeasure()->getCode());
            }

            $items[] = $item;
        }

        return $items;
    }

    public function recomputeTotals(PurchaseOrder $purchaseOrder): void
    {
        $subtotal = Money::money('0.00');
        $taxTotal = Money::money('0.00');

        foreach ($purchaseOrder->getItems() as $item) {
            $subtotal = $subtotal->plus(Money::money($item->getGrossAmount()));
            $taxTotal = $taxTotal->plus(Money::money($item->getTaxAmount()));
        }

        $purchaseOrder->setSubtotal(Money::toString($subtotal));
        $purchaseOrder->setTaxTotal(Money::toString($taxTotal));
        $purchaseOrder->setGrandTotal(Money::toString($subtotal->plus($taxTotal)));
    }

    public function transitionTo(PurchaseOrder $purchaseOrder, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$purchaseOrder->getStatus()] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw ValidationException::withErrors([
                'status' => [sprintf(
                    'No se puede pasar la orden de compra de "%s" a "%s".',
                    $purchaseOrder->getStatus(),
                    $newStatus,
                )],
            ]);
        }

        $purchaseOrder->setStatus($newStatus);
    }

    private function extractProductId(mixed $productRef): ?string
    {
        if ($productRef === null) {
            return null;
        }

        if (is_array($productRef)) {
            $productRef = $productRef['@id'] ?? $productRef['id'] ?? null;
            if ($productRef === null) {
                return null;
            }
        }

        if (!is_string($productRef)) {
            return null;
        }

        if (str_starts_with($productRef, '/api/products/')) {
            return substr($productRef, strlen('/api/products/'));
        }

        return $productRef;
    }
}
