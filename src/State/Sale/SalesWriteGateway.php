<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Util\Money;
use App\Entity\Sale;
use App\Service\SalePaymentPlanService;
use App\Service\SaleCurrencyResolver;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * Gateway for writes on the new sales schema.
 */
readonly class SalesWriteGateway
{
    public function __construct(
        private Connection $connection,
        private SaleCurrencyResolver $saleCurrencyResolver,
        private SalePaymentPlanService $salePaymentPlanService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function write(Sale $sale, ?int $cashSessionId, bool $isNew = false): void
    {
        $saleId = $sale->getId();
        $warehouseId = $sale->getAlmacen()?->getId();
        $cashRegisterId = $sale->getCajaId();
        $sellerUserId = $sale->getCreatedBy() ?? $sale->getUpdatedBy();
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();

        if ($saleId === null) {
            throw new \RuntimeException('New schema write aborted: sale id is null.');
        }
        if ($branchId === null) {
            throw new \RuntimeException(sprintf('New schema write aborted: branch is null for sale %s.', $saleId));
        }
        if ($warehouseId === null) {
            throw new \RuntimeException(sprintf('New schema write aborted: warehouse is null for sale %s.', $saleId));
        }
        if ($cashRegisterId === null) {
            throw new \RuntimeException(
                sprintf('New schema write aborted: cash register is null for sale %s.', $saleId)
            );
        }
        if ($sellerUserId === null) {
            throw new \RuntimeException(sprintf('New schema write aborted: seller user is null for sale %s.', $saleId));
        }

        $subtotal = Money::money($sale->getSubtotal());
        $discountPercent = Money::of($sale->getDescuento() ?? '0');
        $discountAmount = Money::mul($subtotal, Money::rate($discountPercent));
        $grandTotal = Money::money($sale->getTotal());
        $currencyData = $this->saleCurrencyResolver->resolveForWrite($sale);
        $paymentLines = $this->salePaymentPlanService->resolveForWrite(
            $sale,
            $currencyData['currency'],
            Money::toString($grandTotal),
        );
        $paidTotal = BigDecimal::zero();
        $changeTotal = BigDecimal::zero();
        foreach ($paymentLines as $paymentLine) {
            $paidTotal = $paidTotal->plus(Money::of($paymentLine['amount']));
            if ($paymentLine['change_amount'] !== null) {
                $changeTotal = $changeTotal->plus(Money::of($paymentLine['change_amount']));
            }
        }
        $paidTotal = Money::money($paidTotal);
        $changeTotal = Money::money($changeTotal);
        $balanceDue = $grandTotal->isGreaterThan($paidTotal)
            ? Money::money($grandTotal->minus($paidTotal))
            : BigDecimal::zero();
        $issuedAt = $sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable();
        $updatedAt = $sale->getDateUpdated() ?? $sale->getDateCreated();
        $saleOffline = $this->buildOfflineMeta('sale:' . $saleId, $issuedAt, 1);

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO sales (
    id,
    legacy_movimiento_id,
    serie,
    numero,
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
    sync_status,
    local_uuid,
    idempotency_key,
    synced_at,
    device_id,
    version_no,
    last_modified_at
) VALUES (
    :id,
    :legacy_movimiento_id,
    :serie,
    :numero,
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
    :sync_status,
    :local_uuid,
    :idempotency_key,
    :synced_at,
    :device_id,
    :version_no,
    :last_modified_at
)
ON DUPLICATE KEY UPDATE
    branch_id = VALUES(branch_id),
    warehouse_id = VALUES(warehouse_id),
    cash_register_id = VALUES(cash_register_id),
    cash_session_id = VALUES(cash_session_id),
    serie = COALESCE(VALUES(serie), serie),
    numero = COALESCE(VALUES(numero), numero),
    customer_id = VALUES(customer_id),
    seller_user_id = VALUES(seller_user_id),
    status = VALUES(status),
    currency = VALUES(currency),
    exchange_rate = VALUES(exchange_rate),
    subtotal = VALUES(subtotal),
    discount_total = VALUES(discount_total),
    tax_total = VALUES(tax_total),
    grand_total = VALUES(grand_total),
    paid_total = VALUES(paid_total),
    change_total = VALUES(change_total),
    balance_due = VALUES(balance_due),
    issued_at = VALUES(issued_at),
    updated_at = VALUES(updated_at),
    sync_status = VALUES(sync_status),
    synced_at = VALUES(synced_at),
    device_id = COALESCE(VALUES(device_id), device_id),
    version_no = version_no + 1,
    last_modified_at = VALUES(last_modified_at)
SQL,
                [
                    'id' => $saleId,
                    'legacy_movimiento_id' => $saleId,
                    'serie'  => $sale->getSerie(),
                    'numero' => $sale->getNumero(),
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'cash_register_id' => $cashRegisterId,
                    'cash_session_id' => $cashSessionId,
                    'customer_id' => $sale->getCliente()?->getId(),
                    'seller_user_id' => $sellerUserId,
                    'status' => $sale->getEstado() === 0 ? 'VOIDED' : 'COMPLETED',
                    'currency' => $currencyData['currency'],
                    'exchange_rate' => $currencyData['exchange_rate'],
                    'subtotal' => Money::toString($subtotal),
                    'discount_total' => Money::toString($discountAmount),
                    'tax_total' => '0.00',
                    'grand_total' => Money::toString($grandTotal),
                    'paid_total' => Money::toString($paidTotal),
                    'change_total' => Money::toString($changeTotal),
                    'balance_due' => Money::toString($balanceDue),
                    'issued_at' => $issuedAt->format('Y-m-d H:i:s'),
                    'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'updated_at' => $updatedAt?->format('Y-m-d H:i:s'),
                    'sync_status' => 'SYNCED',
                    'local_uuid' => $saleOffline['local_uuid'],
                    'idempotency_key' => $saleOffline['idempotency_key'],
                    'synced_at' => $saleOffline['synced_at'],
                    'device_id' => $saleOffline['device_id'],
                    'version_no' => $saleOffline['version_no'],
                    'last_modified_at' => $saleOffline['last_modified_at'],
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
    updated_at,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at
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
    :updated_at,
    :local_uuid,
    :idempotency_key,
    :sync_status,
    :synced_at,
    :device_id,
    :version_no,
    :last_modified_at
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
                        'local_uuid' => $this->deterministicUuid('sale-item-local:' . $saleId . ':' . ($lineNo - 1)),
                        'idempotency_key' => $this->deterministicUuid('sale-item:' . $saleId . ':' . ($lineNo - 1)),
                        'sync_status' => 'SYNCED',
                        'synced_at' => $issuedAt->format('Y-m-d H:i:s'),
                        'device_id' => null,
                        'version_no' => 1,
                        'last_modified_at' => $issuedAt->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $paymentIdx = 0;
            foreach ($paymentLines as $paymentLine) {
                ++$paymentIdx;
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
    created_at,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at
) VALUES (
    :sale_id,
    :payment_method_id,
    :amount,
    :currency,
    :tendered_amount,
    :change_amount,
    :paid_at,
    :created_at,
    :local_uuid,
    :idempotency_key,
    :sync_status,
    :synced_at,
    :device_id,
    :version_no,
    :last_modified_at
)
SQL,
                    [
                        'sale_id' => $saleId,
                        'payment_method_id' => $paymentLine['payment_method_id'],
                        'amount' => $paymentLine['amount'],
                        'currency' => $paymentLine['currency'],
                        'tendered_amount' => $paymentLine['tendered_amount'],
                        'change_amount' => $paymentLine['change_amount'],
                        'paid_at' => $issuedAt->format('Y-m-d H:i:s'),
                        'created_at' => ($sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        'local_uuid' => $this->deterministicUuid('sale-payment-local:' . $saleId . ':' . $paymentIdx),
                        'idempotency_key' => $this->deterministicUuid('sale-payment:' . $saleId . ':' . $paymentIdx),
                        'sync_status' => 'SYNCED',
                        'synced_at' => $issuedAt->format('Y-m-d H:i:s'),
                        'device_id' => null,
                        'version_no' => 1,
                        'last_modified_at' => $issuedAt->format('Y-m-d H:i:s'),
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
    'Bridge from movimiento.descuento',
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

        if ($isNew && $sale->getEstado() !== 0) {
            $this->upsertDailySummaries($sale);
        }
    }

    private function upsertDailySummaries(Sale $sale): void
    {
        try {
            $issuedAt = ($sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable())
                ->format('Y-m-d H:i:s');
            $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();
            $subtotal = Money::toString(Money::money($sale->getSubtotal()));
            $discountPercent = Money::of($sale->getDescuento() ?? '0');
            $discountAmount = Money::toString(
                Money::mul(Money::money($sale->getSubtotal()), Money::rate($discountPercent))
            );
            $grandTotal = Money::toString(Money::money($sale->getTotal()));

            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO branch_daily_summary (
    summary_date, branch_id, sales_count, gross_sales,
    discount_total, tax_total, net_sales, refunds_total, cash_difference_total
) VALUES (
    DATE(CONVERT_TZ(:issued_at,'UTC','America/Lima')),
    :branch_id, 1, :subtotal, :discount_total, :tax_total, :grand_total, :refund_total, 0
)
ON DUPLICATE KEY UPDATE
  sales_count=sales_count+1,
  gross_sales=gross_sales+VALUES(gross_sales),
  discount_total=discount_total+VALUES(discount_total),
  tax_total=tax_total+VALUES(tax_total),
  net_sales=net_sales+VALUES(net_sales),
  refunds_total=refunds_total+VALUES(refunds_total),
  updated_at=NOW()
SQL,
                [
                    'issued_at' => $issuedAt,
                    'branch_id' => $branchId,
                    'subtotal' => $subtotal,
                    'discount_total' => $discountAmount,
                    'tax_total' => '0.00',
                    'grand_total' => $grandTotal,
                    'refund_total' => '0',
                ]
            );

            $currencyData = $this->saleCurrencyResolver->resolveForWrite($sale);
            $paymentLines = $this->salePaymentPlanService->resolveForWrite(
                $sale,
                $currencyData['currency'],
                Money::toString(Money::money($sale->getTotal())),
            );
            foreach ($paymentLines as $paymentLine) {
                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO payment_method_daily_summary (
    summary_date, branch_id, payment_method_id,
    payment_count, amount_total, refund_amount_total
) VALUES (
    DATE(CONVERT_TZ(:issued_at,'UTC','America/Lima')),
    :branch_id, :payment_method_id, 1, :amount, 0
)
ON DUPLICATE KEY UPDATE
  payment_count=payment_count+1,
  amount_total=amount_total+VALUES(amount_total),
  updated_at=NOW()
SQL,
                    [
                        'issued_at' => $issuedAt,
                        'branch_id' => $branchId,
                        'payment_method_id' => $paymentLine['payment_method_id'],
                        'amount' => $paymentLine['amount'],
                    ]
                );
            }

            $warehouseId = $sale->getAlmacen()?->getId();
            foreach ($sale->getItems() as $item) {
                $product = $item->getProducto();
                if ($product === null || $warehouseId === null) {
                    continue;
                }

                $qty = Money::of($item->getCantidad())->toScale(3, RoundingMode::HALF_UP);
                $netAmount = Money::money($item->getPrecioTotal());
                $costPerUnit = Money::of($product->getPurchasePrice() ?? '0');
                $costTotal = Money::money(
                    $qty
                        ->multipliedBy($costPerUnit)
                        ->toScale(2, RoundingMode::HALF_UP)
                );
                $marginAmount = $netAmount->minus($costTotal);

                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO product_daily_summary (
    summary_date, branch_id, warehouse_id, product_id,
    qty_sold, net_sales, cost_total, margin_total, returns_qty
) VALUES (
    DATE(CONVERT_TZ(:issued_at,'UTC','America/Lima')),
    :branch_id, :warehouse_id, :product_id, :qty, :net_amount, :cost_total, :margin_amount, 0
)
ON DUPLICATE KEY UPDATE
  qty_sold=qty_sold+VALUES(qty_sold),
  net_sales=net_sales+VALUES(net_sales),
  cost_total=cost_total+VALUES(cost_total),
  margin_total=margin_total+VALUES(margin_total),
  updated_at=NOW()
SQL,
                    [
                        'issued_at' => $issuedAt,
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'product_id' => $product->getId(),
                        'qty' => $qty->__toString(),
                        'net_amount' => Money::toString($netAmount),
                        'cost_total' => Money::toString($costTotal),
                        'margin_amount' => Money::toString($marginAmount),
                    ]
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Daily summary upsert failed — sale data may be stale', [
                'sale_id' => $sale->getId(),
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function markVoided(Sale $sale, ?string $reason = null): void
    {
        $saleId = $sale->getId();
        if ($saleId === null) {
            throw new \RuntimeException('New schema void write aborted: sale id is null.');
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
    sync_status = 'SYNCED',
    synced_at = :updated_at,
    version_no = version_no + 1,
    last_modified_at = :updated_at
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

    /**
     * @throws Exception
     */
    public function writeCashSaleMovement(
        string $saleId,
        int $cashSessionId,
        int $createdBy,
        string $amount,
        ?int $categoryId = null,
        ?int $paymentMethodId = null,
        ?\DateTimeImmutable $createdAt = null,
        ?string $description = null,
    ): void {
        $at = $createdAt ?? new \DateTimeImmutable();
        $seed = sprintf('cash-sale:%s:%d', $saleId, $cashSessionId);
        $offline = $this->buildOfflineMeta($seed, $at, 1);

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO cash_movements (
    cash_session_id,
    movement_type,
    category_id,
    sale_id,
    return_id,
    payment_method_id,
    amount,
    description,
    created_by,
    created_at,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at
) VALUES (
    :cash_session_id,
    'SALE_IN',
    :category_id,
    :sale_id,
    NULL,
    :payment_method_id,
    :amount,
    :description,
    :created_by,
    :created_at,
    :local_uuid,
    :idempotency_key,
    'SYNCED',
    :synced_at,
    :device_id,
    :version_no,
    :last_modified_at
)
ON DUPLICATE KEY UPDATE
    amount = VALUES(amount),
    description = VALUES(description),
    synced_at = VALUES(synced_at),
    version_no = version_no + 1,
    last_modified_at = VALUES(last_modified_at)
SQL,
            [
                'cash_session_id' => $cashSessionId,
                'category_id' => $categoryId,
                'sale_id' => $saleId,
                'payment_method_id' => $paymentMethodId,
                'amount' => Money::toString(Money::money($amount)),
                'description' => $description ?? 'Venta en efectivo',
                'created_by' => $createdBy,
                'created_at' => $at->format('Y-m-d H:i:s'),
                'local_uuid' => $offline['local_uuid'],
                'idempotency_key' => $offline['idempotency_key'],
                'synced_at' => $offline['synced_at'],
                'device_id' => $offline['device_id'],
                'version_no' => $offline['version_no'],
                'last_modified_at' => $offline['last_modified_at'],
            ]
        );
    }

    private function buildOfflineMeta(string $seed, \DateTimeImmutable $now, int $version): array
    {
        $timestamp = $now->format('Y-m-d H:i:s');

        return [
            'local_uuid' => $this->deterministicUuid('local:' . $seed),
            'idempotency_key' => $this->deterministicUuid('idem:' . $seed),
            'synced_at' => $timestamp,
            'device_id' => null,
            'version_no' => $version,
            'last_modified_at' => $timestamp,
        ];
    }

    private function deterministicUuid(string $value): string
    {
        $hex = md5($value);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
