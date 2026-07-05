<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleDiscount;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SalePayment;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleTax;
use App\Sales\Repository\DashboardRepository;
use App\Sales\Service\SaleCurrencyResolver;
use App\Sales\Service\SalePaymentPlanService;
use App\Settings\Service\TenantSettingsService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gateway for writes on the new sales schema.
 *
 * Fase 3: all writes to sales, sale_items, sale_payments, sale_taxes and
 * sale_discounts are performed through ORM entities (no raw DBAL INSERT/DELETE
 * for those tables). markVoided() and writeCashSaleMovement() remain DBAL
 * because they target tables without a full ORM entity.
 */
readonly class SalesWriteGateway
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private SaleCurrencyResolver $saleCurrencyResolver,
        private SalePaymentPlanService $salePaymentPlanService,
        private DashboardRepository $dashboardRepository,
        private TenantSettingsService $tenantSettingsService,
    ) {}

    /**
     * @throws Exception
     */
    public function write(SaleRecord $sale, ?int $cashSessionId): void
    {
        $saleId = $sale->getId();
        $warehouseId = $sale->getAlmacen()?->getId();
        $cashRegisterId = $sale->getCajaId();
        $sellerUserId = $sale->getCreatedBy() ?? $sale->getUpdatedBy();
        $branch = $sale->getBranch() ?? $sale->getAlmacen()?->getBranch();
        $branchId = $branch?->getId();

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
            throw new \RuntimeException(sprintf(
                'New schema write aborted: cash register is null for sale %s.',
                $saleId,
            ));
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
        $rawIssuedAt = $sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable();
        $issuedAt = \DateTimeImmutable::createFromInterface($rawIssuedAt);
        $issuedAtMutable = \DateTime::createFromImmutable($issuedAt);
        $limaZone = new \DateTimeZone('America/Lima');
        if ($sale->getIssuedDate() !== null) {
            $issuedDate = $sale->getIssuedDate();
        } elseif ($sale->getFecha() !== null && $issuedAt->format('H:i:s') === '00:00:00') {
            // Legacy clients sent date-only values through issued_at; preserve the literal UTC date.
            $issuedDate = new \DateTimeImmutable($issuedAt->format('Y-m-d'), $limaZone);
        } else {
            $issuedDate = $issuedAt->setTimezone($limaZone)->setTime(0, 0, 0);
        }
        $updatedAt = $sale->getDateUpdated() ?? $sale->getDateCreated();
        $saleOffline = $this->buildOfflineMeta('sale:' . $saleId, $issuedAt, 1);
        $createdAt = $sale->getDateCreated() ?? new \DateTimeImmutable();
        $createdAtMutable = \DateTime::createFromImmutable(\DateTimeImmutable::createFromInterface($createdAt));

        // ── Populate offline/sync fields on SaleRecord ────────────────────────
        $sale->setIssuedAt($issuedAtMutable);
        $sale->setSyncStatus('SYNCED');
        $sale->setLocalUuid($saleOffline['local_uuid']);
        $sale->setIdempotencyKey($saleOffline['idempotency_key']);
        $sale->setSyncedAt($issuedAtMutable);
        $sale->setDeviceId(null);
        // version_no: increment on update, keep 1 on first write
        if ($sale->getVersionNo() < 1) {
            $sale->setVersionNo(1);
        } else {
            $sale->setVersionNo($sale->getVersionNo() + 1);
        }
        $sale->setLastModifiedAt($issuedAtMutable);
        $sale->setIssuedDate($issuedDate);

        // Propagate computed totals so ORM flush writes them
        $sale->setPaidTotal(Money::toString($paidTotal));
        $sale->setChangeTotal(Money::toString($changeTotal));
        $sale->setBalanceDue(Money::toString($balanceDue));
        $sale->setDiscountTotal(Money::toString($discountAmount));
        $sale->setCurrency($currencyData['currency']);
        $sale->setExchangeRate((string) $currencyData['exchange_rate']);
        $sale->setBranch($branch);
        $sale->setSellerUserId($sellerUserId);
        $sale->setCashSessionId($cashSessionId);
        $sale->setStatus($sale->getEstado() === 0 ? 'VOIDED' : 'COMPLETED');
        $sale->setLegacyMovimientoId($saleId);

        // ── Rebuild sale_items with offline meta ──────────────────────────────
        $lineNo = 1;
        foreach ($sale->getSaleItemRecords() as $item) {
            if ($item->getProduct() === null) {
                continue;
            }

            $qty = Money::of($item->getQty())->toScale(3, RoundingMode::HALF_UP);
            $unitPrice = Money::of($item->getUnitPrice())->toScale(4, RoundingMode::HALF_UP);
            $grossAmount = Money::mul(Money::money($qty), Money::money($unitPrice));
            $netAmount = Money::money($item->getNetAmount());
            $currentLineNo = $lineNo++;

            $itemOffline = $this->buildOfflineMeta('sale-item:' . $saleId . ':' . $currentLineNo, $issuedAt, 1);

            $item->setLineNo($currentLineNo);
            $item->setSkuSnapshot($item->getSkuSnapshot() ?? $item->getProduct()->getCode());
            $item->setNameSnapshot(
                $item->getNameSnapshot() !== '' ? $item->getNameSnapshot() : $item->getProduct()->getName(),
            );
            $item->setUomSnapshot($item->getUomSnapshot() ?? $item->getProduct()->getUnitOfMeasure()->getCode());
            $item->setGrossAmount(Money::toString($grossAmount));
            $item->setNetAmount(Money::toString($netAmount));
            $item->setTaxAmount(Money::toString(Money::money($item->getNetAmount())->multipliedBy(Money::of(
                $sale->getIgv() ?? '0',
            )->dividedBy(100, 10, RoundingMode::HALF_UP))));
            $item->setUnitCostSnapshot($item->getUnitCostSnapshot() ?? $item->getProduct()->getPurchasePrice());
            $item->setSyncStatus('SYNCED');
            $item->setLocalUuid($this->deterministicUuid('sale-item-local:' . $saleId . ':' . $currentLineNo));
            $item->setIdempotencyKey($itemOffline['idempotency_key']);
            $item->setSyncedAt($issuedAtMutable);
            $item->setDeviceId(null);
            $item->setVersionNo(1);
            $item->setLastModifiedAt($issuedAtMutable);
            $item->setCreatedAt($createdAtMutable);
            $item->setUpdatedAt($updatedAt !== null ? \DateTime::createFromInterface($updatedAt) : null);
        }

        // ── Rebuild sale_payments ─────────────────────────────────────────────
        $sale->clearSalePayments();
        $paymentIdx = 0;
        foreach ($paymentLines as $paymentLine) {
            ++$paymentIdx;
            $paymentOffline = $this->buildOfflineMeta('sale-payment:' . $saleId . ':' . $paymentIdx, $issuedAt, 1);

            $paymentMethodId = $paymentLine['payment_method_id'];
            $paymentMethod = $this->entityManager->getReference(PaymentMethod::class, $paymentMethodId);

            $payment = new SalePayment()
                ->setSaleRecord($sale)
                ->setPaymentMethod($paymentMethod)
                ->setAmount($paymentLine['amount'])
                ->setCurrency($paymentLine['currency'])
                ->setTenderedAmount($paymentLine['tendered_amount'])
                ->setChangeAmount($paymentLine['change_amount'])
                ->setPaidAt($issuedAtMutable)
                ->setCreatedAt($createdAtMutable)
                ->setSyncStatus('SYNCED')
                ->setLocalUuid($paymentOffline['local_uuid'])
                ->setIdempotencyKey($paymentOffline['idempotency_key'])
                ->setSyncedAt($issuedAtMutable)
                ->setDeviceId(null)
                ->setVersionNo(1)
                ->setLastModifiedAt($issuedAtMutable);

            $sale->addSalePayment($payment);
        }

        // ── Rebuild sale_discounts ────────────────────────────────────────────
        $sale->clearSaleDiscounts();

        // Item-level discounts
        foreach ($sale->getSaleItemRecords() as $item) {
            $itemDiscountPct = Money::of($item->getDiscountPercent() ?? '0');
            if ($itemDiscountPct->isGreaterThan(BigDecimal::zero())) {
                $discount = new SaleDiscount()
                    ->setSaleRecord($sale)
                    ->setSaleItem($item)
                    ->setDiscountType('PERCENT')
                    ->setSource('MANUAL')
                    ->setValue($itemDiscountPct->toScale(4, RoundingMode::HALF_UP)->__toString())
                    ->setAmountApplied(Money::toString(Money::money($item->getDiscountAmount())))
                    ->setReasonCode('LEGACY')
                    ->setReasonText('')
                    ->setCreatedAt($createdAtMutable);

                $sale->addSaleDiscount($discount);
            }
        }

        // Sale-level discount
        if (!$discountAmount->isZero()) {
            $saleDiscount = new SaleDiscount()
                ->setSaleRecord($sale)
                ->setSaleItem(null)
                ->setDiscountType('PERCENT')
                ->setSource('MANUAL')
                ->setValue($discountPercent->toScale(4, RoundingMode::HALF_UP)->__toString())
                ->setAmountApplied(Money::toString($discountAmount))
                ->setReasonCode('LEGACY')
                ->setReasonText('')
                ->setCreatedAt($createdAtMutable);

            $sale->addSaleDiscount($saleDiscount);
        }

        // ── Rebuild sale_taxes ────────────────────────────────────────────────
        $sale->clearSaleTaxes();

        // Use the explicit igv rate from the sale payload; fall back to the
        // tenant-configured IGV rate (e.g. 18.0) so POS sales always generate
        // a SaleTax row even when the client doesn't send the rate explicitly.
        $saleIgvRaw = $sale->getIgv() ?? '0';
        $igvRateValue = Money::of($saleIgvRaw)->isGreaterThan(BigDecimal::zero())
            ? (float) $saleIgvRaw
            : $this->tenantSettingsService->getIgv();

        if ($igvRateValue > 0) {
            $igvRate = Money::of((string) $igvRateValue)->toScale(4, RoundingMode::HALF_UP);
            $taxAmount = number_format(
                round((float) Money::toString($subtotal) * ($igvRateValue / 100), 2),
                2,
                '.',
                '',
            );

            $saleTax = new SaleTax()
                ->setSaleRecord($sale)
                ->setSaleItem(null)
                ->setTaxCode('IGV')
                ->setTaxRate($igvRate->__toString())
                ->setTaxableBase(Money::toString($grandTotal))
                ->setTaxAmount($taxAmount)
                ->setCreatedAt($createdAtMutable);

            $sale->addSaleTax($saleTax);

            // Also update SaleRecord.taxTotal to reflect the computed IGV amount.
            if ((float) $sale->getTaxTotal() === 0.0) {
                $sale->setTaxTotal($taxAmount);
            }
        }

        // ── Flush all ORM changes ─────────────────────────────────────────────
        // Ensure $sale is managed (new entities are not yet tracked until persisted).
        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        // Post-commit: update daily summary (non-voided sales only).
        if ($sale->getEstado() !== 0) {
            try {
                $this->upsertDailySummaries((string) $branchId, $issuedDate);
            } catch (\Throwable) {
                // Intentionally swallowed: summary is best-effort.
            }

            try {
                $this->dashboardRepository->invalidate((string) $branchId);
            } catch (\Throwable) {
                // Best-effort cache invalidation — never break sale write.
            }
        }
    }

    /**
     * Re-aggregates branch_daily_summary for the given branch + date from the sales table.
     *
     * @throws Exception
     */
    private function upsertDailySummaries(string $branchId, \DateTimeImmutable $saleDate): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO branch_daily_summary
                    (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
                SELECT
                    :saleDate                                 AS summary_date,
                    :branchId                                 AS branch_id,
                    COUNT(*)                                  AS sales_count,
                    COALESCE(SUM(grand_total), 0)             AS gross_sales,
                    COALESCE(SUM(discount_total), 0)          AS discount_total,
                    COALESCE(SUM(tax_total), 0)               AS tax_total,
                    COALESCE(SUM(grand_total - tax_total), 0) AS net_sales,
                    0                                         AS refunds_total
                FROM sales
                WHERE issued_date = :saleDate
                  AND branch_id  = :branchId
                  AND status    != 'VOIDED'
                ON DUPLICATE KEY UPDATE
                    sales_count    = VALUES(sales_count),
                    gross_sales    = VALUES(gross_sales),
                    discount_total = VALUES(discount_total),
                    tax_total      = VALUES(tax_total),
                    net_sales      = VALUES(net_sales),
                    updated_at     = NOW()
                SQL,
            [
                'saleDate' => $saleDate->format('Y-m-d'),
                'branchId' => $branchId,
            ],
        );
    }

    /**
     * @throws Exception
     */
    public function markVoided(SaleRecord $sale, ?string $reason = null): void
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
            ],
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
            ],
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

        $existingCashMovementId = $this->connection->fetchOne('SELECT id FROM cash_movements WHERE idempotency_key = :idempotency_key', [
            'idempotency_key' => $offline['idempotency_key'],
        ]);
        if ($existingCashMovementId !== false && $existingCashMovementId !== null) {
            return;
        }

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
            ],
        );
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
