<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Util\Money;
use App\Entity\Sale;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;

readonly class SalesV2DualWriteService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function syncSale(Sale $sale, int $cashSessionId): void
    {
        $saleId = $sale->getId();
        $warehouseId = $sale->getAlmacen()?->getId();
        $cashRegisterId = $sale->getCajaId();
        $sellerUserId = $sale->getCreatedBy() ?? $sale->getUpdatedBy();
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();

        if ($saleId === null) {
            throw new \RuntimeException('Dual-write aborted: sale id is null.');
        }
        if ($branchId === null) {
            throw new \RuntimeException(sprintf('Dual-write aborted: branch is null for sale %s.', $saleId));
        }
        if ($warehouseId === null) {
            throw new \RuntimeException(sprintf('Dual-write aborted: warehouse is null for sale %s.', $saleId));
        }
        if ($cashRegisterId === null) {
            throw new \RuntimeException(sprintf('Dual-write aborted: cash register is null for sale %s.', $saleId));
        }
        if ($sellerUserId === null) {
            throw new \RuntimeException(sprintf('Dual-write aborted: seller user is null for sale %s.', $saleId));
        }

        $subtotal = Money::money($sale->getSubtotal());
        $discountPercent = Money::of($sale->getDescuento() ?? '0');
        $discountAmount = Money::mul($subtotal, Money::rate($discountPercent));
        $grandTotal = Money::money($sale->getTotal());
        $paidTotal = $grandTotal;
        $balanceDue = BigDecimal::zero();
        $issuedAt = $sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable();
        $updatedAt = $sale->getDateUpdated() ?? $sale->getDateCreated();

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO sales (
    id,
    legacy_movimiento_id,
    branch_id,
    warehouse_id,
    cash_register_id,
    cash_session_id,
    customer_id,
    seller_user_id,
    status,
    currency,
    exchange_rate,
    subtotal,
    discount_total,
    tax_total,
    grand_total,
    paid_total,
    change_total,
    balance_due,
    issued_at,
    created_at,
    updated_at,
    sync_status
) VALUES (
    :id,
    :legacy_movimiento_id,
    :branch_id,
    :warehouse_id,
    :cash_register_id,
    :cash_session_id,
    :customer_id,
    :seller_user_id,
    :status,
    :currency,
    :exchange_rate,
    :subtotal,
    :discount_total,
    :tax_total,
    :grand_total,
    :paid_total,
    :change_total,
    :balance_due,
    :issued_at,
    :created_at,
    :updated_at,
    :sync_status
)
ON DUPLICATE KEY UPDATE
    branch_id = VALUES(branch_id),
    warehouse_id = VALUES(warehouse_id),
    cash_register_id = VALUES(cash_register_id),
    cash_session_id = VALUES(cash_session_id),
    customer_id = VALUES(customer_id),
    seller_user_id = VALUES(seller_user_id),
    status = VALUES(status),
    subtotal = VALUES(subtotal),
    discount_total = VALUES(discount_total),
    tax_total = VALUES(tax_total),
    grand_total = VALUES(grand_total),
    paid_total = VALUES(paid_total),
    change_total = VALUES(change_total),
    balance_due = VALUES(balance_due),
    issued_at = VALUES(issued_at),
    updated_at = VALUES(updated_at),
    sync_status = VALUES(sync_status)
SQL,
                [
                    'id' => $saleId,
                    'legacy_movimiento_id' => $saleId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'cash_register_id' => $cashRegisterId,
                    'cash_session_id' => $cashSessionId,
                    'customer_id' => $sale->getCliente()?->getId(),
                    'seller_user_id' => $sellerUserId,
                    'status' => $sale->getEstado() === 0 ? 'VOIDED' : 'COMPLETED',
                    'currency' => 'PEN',
                    'exchange_rate' => '1.000000',
                    'subtotal' => Money::toString($subtotal),
                    'discount_total' => Money::toString($discountAmount),
                    'tax_total' => '0.00',
                    'grand_total' => Money::toString($grandTotal),
                    'paid_total' => Money::toString($paidTotal),
                    'change_total' => '0.00',
                    'balance_due' => Money::toString($balanceDue),
                    'issued_at' => $issuedAt->format('Y-m-d H:i:s'),
                    'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'updated_at' => $updatedAt?->format('Y-m-d H:i:s'),
                    'sync_status' => 'SYNCED',
                ]
            );

            $this->connection->executeStatement(
                'DELETE FROM sale_taxes WHERE sale_id = :sale_id',
                ['sale_id' => $saleId]
            );
            $this->connection->executeStatement(
                'DELETE FROM sale_discounts WHERE sale_id = :sale_id',
                ['sale_id' => $saleId]
            );
            $this->connection->executeStatement(
                'DELETE FROM sale_payments WHERE sale_id = :sale_id',
                ['sale_id' => $saleId]
            );
            $this->connection->executeStatement(
                'DELETE FROM sale_items WHERE sale_id = :sale_id',
                ['sale_id' => $saleId]
            );

            $lineNo = 1;
            foreach ($sale->getItems() as $item) {
                if ($item->getProducto() === null) {
                    continue;
                }

                $qty = Money::of($item->getCantidad())->toScale(3, RoundingMode::HALF_UP);
                $unitPrice = Money::of($item->getPrecioUnitario() ?? '0')->toScale(4, RoundingMode::HALF_UP);
                $grossAmount = Money::mul(Money::money($qty), Money::money($unitPrice));
                $itemDiscount = Money::money($item->getDescuento());
                $netAmount = Money::money($item->getPrecioTotal());

                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sale_items (
    sale_id,
    legacy_movimiento_detalle_id,
    line_no,
    product_id,
    variant_id,
    sku_snapshot,
    name_snapshot,
    uom_snapshot,
    qty,
    unit_price,
    gross_amount,
    discount_amount,
    tax_amount,
    net_amount,
    unit_cost_snapshot,
    margin_amount,
    created_at,
    updated_at
) VALUES (
    :sale_id,
    :legacy_movimiento_detalle_id,
    :line_no,
    :product_id,
    :variant_id,
    :sku_snapshot,
    :name_snapshot,
    :uom_snapshot,
    :qty,
    :unit_price,
    :gross_amount,
    :discount_amount,
    :tax_amount,
    :net_amount,
    :unit_cost_snapshot,
    :margin_amount,
    :created_at,
    :updated_at
)
SQL,
                    [
                        'sale_id' => $saleId,
                        'legacy_movimiento_detalle_id' => $item->getId(),
                        'line_no' => $lineNo++,
                        'product_id' => $item->getProducto()->getId(),
                        'variant_id' => null,
                        'sku_snapshot' => $item->getProducto()->getCode(),
                        'name_snapshot' => $item->getProducto()->getName(),
                        'uom_snapshot' => $item->getProducto()->getUnitOfMeasure()->getCode(),
                        'qty' => $qty->__toString(),
                        'unit_price' => $unitPrice->__toString(),
                        'gross_amount' => Money::toString($grossAmount),
                        'discount_amount' => Money::toString($itemDiscount),
                        'tax_amount' => '0.00',
                        'net_amount' => Money::toString($netAmount),
                        'unit_cost_snapshot' => $item->getProducto()->getPurchasePrice(),
                        'margin_amount' => null,
                        'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'updated_at' => $updatedAt?->format('Y-m-d H:i:s'),
                    ]
                );
            }

            if ($sale->getTipoPago()->getId() !== null) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sale_payments (
    sale_id,
    payment_method_id,
    amount,
    currency,
    tendered_amount,
    change_amount,
    paid_at,
    created_at
) VALUES (
    :sale_id,
    :payment_method_id,
    :amount,
    :currency,
    :tendered_amount,
    :change_amount,
    :paid_at,
    :created_at
)
SQL,
                    [
                        'sale_id' => $saleId,
                        'payment_method_id' => $sale->getTipoPago()->getId(),
                        'amount' => Money::toString($grandTotal),
                        'currency' => 'PEN',
                        'tendered_amount' => null,
                        'change_amount' => null,
                        'paid_at' => $issuedAt->format('Y-m-d H:i:s'),
                        'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]
                );
            }

            if (!$discountAmount->isZero()) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sale_discounts (
    sale_id,
    sale_item_id,
    discount_type,
    source,
    value,
    amount_applied,
    reason_code,
    reason_text,
    created_at
) VALUES (
    :sale_id,
    NULL,
    'PERCENT',
    'MANUAL',
    :value,
    :amount_applied,
    'LEGACY',
    'Dual-write from movimiento.descuento',
    :created_at
)
SQL,
                    [
                        'sale_id' => $saleId,
                        'value' => $discountPercent->toScale(4, RoundingMode::HALF_UP)->__toString(),
                        'amount_applied' => Money::toString($discountAmount),
                        'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]
                );
            }

            if (Money::of($sale->getIgv() ?? '0')->isGreaterThan(BigDecimal::zero())) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO sale_taxes (
    sale_id,
    sale_item_id,
    tax_code,
    tax_rate,
    taxable_base,
    tax_amount,
    created_at
) VALUES (
    :sale_id,
    NULL,
    'IGV',
    :tax_rate,
    :taxable_base,
    :tax_amount,
    :created_at
)
SQL,
                    [
                        'sale_id' => $saleId,
                        'tax_rate' => Money::of($sale->getIgv() ?? '0')
                            ->toScale(4, RoundingMode::HALF_UP)
                            ->__toString(),
                        'taxable_base' => Money::toString($grandTotal),
                        'tax_amount' => '0.00',
                        'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function markSaleVoided(Sale $sale, ?string $reason = null): void
    {
        $saleId = $sale->getId();
        if ($saleId === null) {
            throw new \RuntimeException('Dual-write void aborted: sale id is null.');
        }

        $updatedAt = $sale->getDateUpdated() ?? new \DateTimeImmutable();
        $voidReason = trim((string) ($reason ?? $sale->getGlosa() ?? 'VOID'));
        if ($voidReason === '') {
            $voidReason = 'VOID';
        }

        $this->connection->executeStatement(
            <<<'SQL'
UPDATE sales
SET
    status = 'VOIDED',
    updated_at = :updated_at,
    sync_status = 'SYNCED'
WHERE id = :id
   OR legacy_movimiento_id = :id
SQL,
            [
                'id' => $saleId,
                'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            ]
        );

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO sale_discounts (
    sale_id,
    sale_item_id,
    discount_type,
    source,
    value,
    amount_applied,
    reason_code,
    reason_text,
    created_at
)
SELECT
    s.id,
    NULL,
    'AMOUNT',
    'MANUAL',
    0,
    0,
    'VOID_REASON',
    :reason_text,
    :created_at
FROM sales s
WHERE (s.id = :id OR s.legacy_movimiento_id = :id)
  AND NOT EXISTS (
    SELECT 1
    FROM sale_discounts d
    WHERE d.sale_id = s.id
      AND d.reason_code = 'VOID_REASON'
  )
SQL,
            [
                'id' => $saleId,
                'reason_text' => $voidReason,
                'created_at' => $updatedAt->format('Y-m-d H:i:s'),
            ]
        );
    }
}
