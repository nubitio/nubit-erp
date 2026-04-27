<?php

declare(strict_types=1);

namespace App\State\Purchase;

use App\Catalog\Entity\UnitOfMeasure;
use App\Core\Util\Money;
use App\Sales\Entity\SaleRecord;
use App\Service\SaleCurrencyResolver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Uid\Uuid;

final readonly class GoodsReceiptWriteGateway
{
    public function __construct(
        private Connection $connection,
        private SaleCurrencyResolver $saleCurrencyResolver,
    ) {
    }

    /**
     * @throws Exception
     */
    public function write(SaleRecord $movement): void
    {
        $receiptId = $movement->getId() ?? Uuid::v7()->toRfc4122();
        $branchId = $movement->getBranch()?->getId() ?? $movement->getAlmacen()?->getBranch()?->getId();
        $warehouseId = $movement->getAlmacen()?->getId();
        $buyerUserId = $movement->getCreatedBy() ?? $movement->getUpdatedBy();

        if ($branchId === null) {
            throw new \RuntimeException(sprintf(
                'Goods receipt write aborted: branch is null for movimiento %s.',
                $receiptId
            ));
        }
        if ($warehouseId === null) {
            throw new \RuntimeException(sprintf(
                'Goods receipt write aborted: warehouse is null for movimiento %s.',
                $receiptId
            ));
        }
        if ($buyerUserId === null) {
            throw new \RuntimeException(sprintf(
                'Goods receipt write aborted: buyer user is null for movimiento %s.',
                $receiptId
            ));
        }

        $subtotal = Money::money($movement->getSubtotal());
        $grandTotal = Money::money($movement->getTotal());
        $currencyData = $this->saleCurrencyResolver->resolveForWrite($movement);
        $rawIssuedAt = $movement->getFecha() ?? $movement->getDateCreated() ?? new \DateTimeImmutable();
        $issuedAt = \DateTimeImmutable::createFromInterface($rawIssuedAt);
        $createdAt = $movement->getDateCreated() !== null ? \DateTimeImmutable::createFromInterface($movement->getDateCreated()) : $issuedAt;
        $updatedAt = $movement->getDateUpdated() !== null ? \DateTimeImmutable::createFromInterface($movement->getDateUpdated()) : $createdAt;
        $offline = $this->buildOfflineMeta('goods-receipt:' . $receiptId, $issuedAt, 1);

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO goods_receipts (
    id,
    legacy_movimiento_id,
    branch_id,
    warehouse_id,
    supplier_id,
    buyer_user_id,
    status,
    currency,
    exchange_rate,
    subtotal,
    tax_total,
    grand_total,
    note,
    issued_at,
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
    :id,
    :legacy_movimiento_id,
    :branch_id,
    :warehouse_id,
    :supplier_id,
    :buyer_user_id,
    :status,
    :currency,
    :exchange_rate,
    :subtotal,
    :tax_total,
    :grand_total,
    :note,
    :issued_at,
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
ON DUPLICATE KEY UPDATE
    branch_id = VALUES(branch_id),
    warehouse_id = VALUES(warehouse_id),
    supplier_id = VALUES(supplier_id),
    buyer_user_id = VALUES(buyer_user_id),
    status = VALUES(status),
    currency = VALUES(currency),
    exchange_rate = VALUES(exchange_rate),
    subtotal = VALUES(subtotal),
    tax_total = VALUES(tax_total),
    grand_total = VALUES(grand_total),
    note = VALUES(note),
    issued_at = VALUES(issued_at),
    updated_at = VALUES(updated_at),
    synced_at = VALUES(synced_at),
    device_id = COALESCE(VALUES(device_id), device_id),
    version_no = version_no + 1,
    last_modified_at = VALUES(last_modified_at)
SQL,
                [
                    'id' => $receiptId,
                    'legacy_movimiento_id' => $receiptId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'supplier_id' => $movement->getProveedor()?->getId(),
                    'buyer_user_id' => $buyerUserId,
                    'status' => $movement->getEstado() === 0 ? 'VOIDED' : 'POSTED',
                    'currency' => $currencyData['currency'],
                    'exchange_rate' => $currencyData['exchange_rate'],
                    'subtotal' => Money::toString($subtotal),
                    'tax_total' => '0.00',
                    'grand_total' => Money::toString($grandTotal),
                    'note' => $movement->getGlosa(),
                    'issued_at' => $issuedAt->format('Y-m-d H:i:s'),
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
                    'local_uuid' => $offline['local_uuid'],
                    'idempotency_key' => $offline['idempotency_key'],
                    'sync_status' => 'SYNCED',
                    'synced_at' => $offline['synced_at'],
                    'device_id' => $offline['device_id'],
                    'version_no' => $offline['version_no'],
                    'last_modified_at' => $offline['last_modified_at'],
                ]
            );

            $this->connection->executeStatement(
                'DELETE FROM goods_receipt_items WHERE goods_receipt_id = :goods_receipt_id',
                ['goods_receipt_id' => $receiptId]
            );

            $lineNo = 0;
            foreach ($movement->getItems() as $item) {
                if ($item->getProducto() === null) {
                    continue;
                }

                ++$lineNo;
                $qty = Money::of($item->getCantidad())->toScale(3)->__toString();
                $unitCost = Money::of($item->getPrecioUnitario() ?? '0')->toScale(4)->__toString();
                $total = Money::money($item->getPrecioTotal());
                $itemOffline = $this->buildOfflineMeta(
                    sprintf('goods-receipt-item:%s:%d', $receiptId, $lineNo),
                    $issuedAt,
                    1
                );

                $this->connection->executeStatement(
                    <<<'SQL'
INSERT INTO goods_receipt_items (
    goods_receipt_id,
    legacy_movimiento_detalle_id,
    line_no,
    product_id,
    variant_id,
    sku_snapshot,
    name_snapshot,
    uom_snapshot,
    qty,
    unit_cost,
    gross_amount,
    tax_amount,
    total_amount,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at,
    created_at,
    updated_at
) VALUES (
    :goods_receipt_id,
    :legacy_movimiento_detalle_id,
    :line_no,
    :product_id,
    :variant_id,
    :sku_snapshot,
    :name_snapshot,
    :uom_snapshot,
    :qty,
    :unit_cost,
    :gross_amount,
    :tax_amount,
    :total_amount,
    :local_uuid,
    :idempotency_key,
    'SYNCED',
    :synced_at,
    :device_id,
    :version_no,
    :last_modified_at,
    :created_at,
    :updated_at
)
SQL,
                    [
                        'goods_receipt_id' => $receiptId,
                        'legacy_movimiento_detalle_id' => $item->getId(),
                        'line_no' => $lineNo,
                        'product_id' => $item->getProducto()->getId(),
                        'variant_id' => null,
                        'sku_snapshot' => $item->getProducto()->getCode(),
                        'name_snapshot' => $item->getProducto()->getName(),
                        'uom_snapshot' => $item->getProducto()->getUnitOfMeasure()->getCode(),
                        'qty' => $qty,
                        'unit_cost' => $unitCost,
                        'gross_amount' => Money::toString($total),
                        'tax_amount' => '0.00',
                        'total_amount' => Money::toString($total),
                        'local_uuid' => $itemOffline['local_uuid'],
                        'idempotency_key' => $itemOffline['idempotency_key'],
                        'synced_at' => $itemOffline['synced_at'],
                        'device_id' => $itemOffline['device_id'],
                        'version_no' => $itemOffline['version_no'],
                        'last_modified_at' => $itemOffline['last_modified_at'],
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
                    ]
                );
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
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
