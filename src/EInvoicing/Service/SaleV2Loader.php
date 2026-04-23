<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Customer;
use App\Entity\SaleRecord;
use App\Entity\SaleItem;
use App\Entity\Warehouse;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads a V2 sale (from the `sales` + `sale_items` tables) and hydrates
 * a synthetic Sale entity so that existing services (MakeInvoice, SaleVoidUseCase)
 * can operate without a movimiento row.
 *
 * This is transitional infrastructure for the v2_authoritative migration path.
 */
readonly class SaleV2Loader
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private SettingsService $settings,
    ) {
    }

    /**
     * Load a Sale entity from the `sales` table, or null if not found.
     *
     * The returned Sale is NOT an ORM-managed entity (it has no identity map entry).
     * It is intended for read-only use by services that query data off the Sale object.
     */
    public function loadAsSale(string $saleId): ?SaleRecord
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT
    s.id,
    s.legacy_movimiento_id,
    s.customer_id,
    s.warehouse_id,
    s.branch_id,
    s.seller_user_id,
    s.status,
    s.currency,
    s.exchange_rate,
    s.subtotal,
    s.tax_total,
    s.discount_total,
    s.grand_total,
    s.issued_at,
    s.created_at,
    s.series,
    s.number
FROM sales s
WHERE s.id = :id
   OR s.legacy_movimiento_id = :id
ORDER BY CASE WHEN s.id = :id THEN 0 ELSE 1 END
LIMIT 1
SQL,
            ['id' => $saleId]
        );

        if ($row === false) {
            return null;
        }

        $sale = new SaleRecord();
        $sale->setId($row['id']);
        $sale->setTipoId(2); // sales table only stores operational sales

        if ($row['issued_at']) {
            $sale->setFecha(new \DateTimeImmutable($row['issued_at'], new \DateTimeZone('UTC')));
        }

        if ($row['created_at']) {
            $sale->setDateCreated(new \DateTimeImmutable($row['created_at']));
        }

        $sale->setCurrency($row['currency']);
        $sale->setExchangeRate($row['exchange_rate']);
        $sale->setSubtotal($row['subtotal']);
        $sale->setTotal($row['grand_total']);
        $sale->setEstado($row['status'] === 'VOIDED' ? 0 : 1);

        if (!empty($row['series'])) {
            $sale->setSerie((string) $row['series']);
        }

        if (!empty($row['number'])) {
            $sale->setNumero((string) $row['number']);
        }

        // Derive IGV rate from stored tax_total; fall back to configured rate.
        $taxRate = $this->resolveIgvRate($row['tax_total'], $row['subtotal']);
        $sale->setIgv($taxRate);

        if ($row['customer_id']) {
            $customer = $this->entityManager->getReference(Customer::class, (int) $row['customer_id']);
            if ($customer instanceof Customer) {
                $sale->setCliente($customer);
            }
        }

        if ($row['warehouse_id']) {
            $warehouse = $this->entityManager->getRepository(Warehouse::class)->find($row['warehouse_id']);
            if ($warehouse instanceof Warehouse) {
                $sale->setAlmacen($warehouse);
                $sale->setBranch($warehouse->getBranch());
            }
        }

        if ($row['seller_user_id']) {
            $sale->setCreatedBy((int) $row['seller_user_id']);
        }

        // Load line items from sale_items table
        $itemRows = $this->connection->fetchAllAssociative(
            'SELECT * FROM sale_items WHERE sale_id = :sale_id ORDER BY line_no',
            ['sale_id' => $row['id']]
        );

        foreach ($itemRows as $itemRow) {
            $saleItem = $this->buildSaleItem($itemRow);
            if ($saleItem !== null) {
                $sale->addLegacyItem($saleItem);
            }
        }

        return $sale;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildSaleItem(array $row): ?SaleItem
    {
        $productId = $row['product_id'] ?? null;
        if (!$productId) {
            return null;
        }

        $product = $this->entityManager->getRepository(\App\Entity\Product::class)->find($productId);
        if ($product === null) {
            return null;
        }

        $item = new SaleItem();
        $item->setProducto($product);
        $item->setCantidad((float) ($row['qty'] ?? 0));
        $item->setPrecioUnitario((string) ($row['unit_price'] ?? '0'));
        $item->setPrecioTotal((string) ($row['net_amount'] ?? '0'));
        $item->setDescuento((string) ($row['discount_amount'] ?? '0'));

        return $item;
    }

    /**
     * Derive IGV rate from stored amounts; fall back to configured rate.
     * If tax_total > 0 and subtotal > 0, derive: round(tax_total/subtotal*100).
     * Otherwise return the configured `app_option_igv` value.
     */
    private function resolveIgvRate(mixed $taxTotal, mixed $subtotal): string
    {
        $tax = (float) ($taxTotal ?? 0);
        $sub = (float) ($subtotal ?? 0);

        if ($tax > 0 && $sub > 0) {
            return (string) (int) round($tax / $sub * 100);
        }

        return (string) ($this->settings->get(Constants::TAX) ?? '18');
    }
}
