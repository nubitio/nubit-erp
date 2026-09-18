<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Shared\ErrorMessages;
use App\Catalog\Entity\UnitOfMeasure;
use App\Sales\Domain\DocType;
use App\Sales\Domain\RequestedVoucherType;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleCurrencyResolver;
use App\Settings\Enum\TaxRegime;
use App\Settings\Service\TenantSettingsService;
use Brick\Math\Exception\MathException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Throwable;

final readonly class InvoiceCreationService implements InvoiceCreationServiceInterface
{
    public function __construct(
        private Connection $connection,
        private SaleCurrencyResolver $saleCurrencyResolver,
        private TenantSettingsService $tenantSettingsService,
    ) {}

    /**
     * @param RequestedVoucherType|null $requestedVoucherTypeOverride when given, takes
     *        precedence over the sale's persisted requested voucher type — used by the
     *        Ventas module's "Generar comprobante" action, which lets the user pick
     *        Boleta/Factura at invoice time rather than at sale creation.
     *
     * @return array{success: bool, message: string, invoiceId?: string}
     *
     * @throws Exception
     * @throws MathException
     * @throws \DateMalformedStringException
     */
    public function createForSale(SaleRecord $sale, ?RequestedVoucherType $requestedVoucherTypeOverride = null): array
    {
        $saleId = $sale->getId();
        if ($saleId === null || $saleId === '') {
            return [
                'success' => false,
                'message' => 'La venta no tiene un identificador válido.',
            ];
        }

        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_ALREADY_HAS_VOUCHER,
            ];
        }

        $issueDate = $sale->getIssuedDate();
        if (!$issueDate) {
            $saleDate = $sale->getFecha();
            if ($saleDate) {
                $saleDateUtc = \DateTimeImmutable::createFromInterface($saleDate)->setTimezone(new DateTimeZone('UTC'));
                $issueDate = $saleDateUtc->format('H:i:s') === '00:00:00'
                    ? $saleDateUtc
                    : $saleDateUtc->setTimezone(new DateTimeZone('America/Lima'));
            }
        }

        if (!$issueDate) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_HAS_NO_VALID_DATE,
            ];
        }

        $todayLima = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        if ($issueDate->format('Y-m-d') < $todayLima->modify('-3 days')->format('Y-m-d')) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_DATE_EXCEEDS_LIMIT,
            ];
        }

        $connection = $this->connection;

        $localId = $connection->fetchOne('select local_id from almacen where almacen_id = ?', [$sale->getAlmacen()?->getId()]);

        $lines = [];
        foreach ($sale->getSaleItemRecords() as $item) {
            $lines[] = new InvoiceLineInput(
                quantity: $item->getQty(),
                unitPrice: $item->getUnitPrice(),
                discountPercent: $item->getDiscountPercent() ?? '0',
            );
        }

        $taxRegime = $this->tenantSettingsService->getTaxRegime();
        $taxPercentage = $taxRegime === TaxRegime::NRUS ? 0.0 : (float) ($sale->getIgv() ?? 0);

        $invoiceCalculator = new InvoiceCalculator(
            $lines,
            $sale->getDescuento() ?? '0',
            (string) $taxPercentage,
            (string) ($sale->getRecargoConsumo() ?? '0'),
            $sale->getTotal() !== null ? (string) $sale->getTotal() : null,
        );
        $invoiceAmounts = $invoiceCalculator->calculate();
        $saleId = $sale->getId();
        if ($saleId === null) {
            return [
                'success' => false,
                'message' => 'La venta no tiene identificador para emitir comprobante.',
            ];
        }
        $currencyData = $this->saleCurrencyResolver->resolveForSaleId($saleId);

        // Resolve the V2 sale_id (sales.id) for the FK to the sales table.
        // Must happen before the dedup check so it can be used in the WHERE clause.
        $v2SaleId = $connection->fetchOne('SELECT id FROM sales WHERE id = :id LIMIT 1', [
            'id' => $saleId,
        ]);
        $resolvedV2SaleId = is_string($v2SaleId) && $v2SaleId !== '' ? $v2SaleId : null;

        $requestedType = $requestedVoucherTypeOverride ?? $sale->getRequestedVoucherType();
        $ruc = $sale->getCliente()?->getRuc() ?? '';

        if ($taxRegime === TaxRegime::NRUS && $requestedType === RequestedVoucherType::FACTURA) {
            return [
                'success' => false,
                'message' => ErrorMessages::NRUS_CANNOT_ISSUE_FACTURA,
            ];
        }

        if ($requestedType === RequestedVoucherType::FACTURA && strlen($ruc) !== 11) {
            return [
                'success' => false,
                'message' => ErrorMessages::VOUCHER_TYPE_REQUIRES_RUC,
            ];
        }

        $connection->beginTransaction();
        try {
            if ($taxRegime === TaxRegime::NRUS) {
                $voucherType = DocType::BOLETA->value;
            } elseif ($requestedType === RequestedVoucherType::FACTURA) {
                $voucherType = DocType::FACTURA->value;
            } elseif ($requestedType === RequestedVoucherType::BOLETA) {
                $voucherType = DocType::BOLETA->value;
            } else {
                $voucherType = strlen($ruc) === 11 ? DocType::FACTURA->value : DocType::BOLETA->value;
            }

            $existingInvoice = $connection->fetchOne('SELECT tax_document_id
                 FROM tax_document
                 WHERE (movimiento_id = :saleId
                    OR (:v2SaleId IS NOT NULL AND sale_id = :v2SaleId))
                   AND document_type IN (:facturaDocType, :boletaDocType)
                 LIMIT 1 FOR UPDATE', [
                'saleId' => $saleId,
                'v2SaleId' => $resolvedV2SaleId,
                'facturaDocType' => DocType::FACTURA->value,
                'boletaDocType' => DocType::BOLETA->value,
            ]);
            if (is_string($existingInvoice) && $existingInvoice !== '') {
                $connection->commit();

                return [
                    'success' => false,
                    'message' => ErrorMessages::SALE_ALREADY_HAS_VOUCHER,
                ];
            }

            $series = $connection->fetchOne('SELECT series
                        FROM local_comprobante
                        WHERE local_id = :localId
                          AND tipo_comprobante_id = :voucherType
                        LIMIT 1', [
                'localId' => $localId,
                'voucherType' => $voucherType,
            ]);

            if (!$series) {
                $connection->rollBack();

                return [
                    'success' => false,
                    'message' => ErrorMessages::NO_SERIES_CONFIGURED,
                ];
            }

            $nextNumber = $connection->fetchOne('SELECT IFNULL(MAX(CAST(number AS SIGNED)), 0) + 1
                 FROM tax_document
                 WHERE series = :series
                   AND document_type = :voucherType
                 FOR UPDATE', [
                'series' => $series,
                'voucherType' => $voucherType,
            ]);

            $taxDocumentId = (string) $this->connection->fetchOne('SELECT UUID()');

            $connection->insert('tax_document', [
                'tax_document_id' => $taxDocumentId,
                'movimiento_id' => $saleId,
                'sale_id' => $resolvedV2SaleId,
                'issue_date' => $issueDate->format('Y-m-d'),
                'document_type' => $voucherType,
                'series' => $series,
                'number' => strval($nextNumber),
                'customer_id' => $sale->getCliente()?->getId(),
                'currency' => $currencyData['currency'],
                'exchange_rate' => $currencyData['exchange_rate'],
                'payment_method' => $sale->getFormaPago(),
                'subtotal' => $invoiceAmounts->getSubtotal(),
                'taxable_amount' => $invoiceAmounts->getTaxedAmount(),
                'igv_amount' => $invoiceAmounts->getTax(),
                'rc_amount' => $invoiceAmounts->getChargesAmount(),
                'total' => $invoiceAmounts->getTotal(),
                'operation_type' => $sale->getTipoOperacion(),
                'detraction_code' => $sale->getDetraccionCodigo(),
                'detraction_percent' => $sale->getDetraccionPorcentaje(),
                'detraction_amount' => $sale->getDetraccionMonto(),
                'detraction_payment_method' => $sale->getDetraccionMedioPago(),
                'detraction_value_ref' => $sale->getDetraccionValorReferencial(),
                'discount_percent' => $sale->getDescuento() ?? 0.0,
                'discount_amount' => $invoiceAmounts->getDiscountAmount(),
                'created_at' => $sale->getDateCreated()?->format('Y-m-d H:i:s'),
                'branch_id' => $sale->getBranch()?->getId(),
            ]);

            $saleItems = array_values($sale->getSaleItemRecords()->toArray());
            $lineNumber = 0;
            foreach ($invoiceAmounts->getLines() as $line) {
                $saleItem = $saleItems[$lineNumber] ?? null;
                $lineNumber++;
                $connection->insert('tax_document_line', [
                    'tax_document_id' => $taxDocumentId,
                    'line_number' => $lineNumber,
                    'product_id' => $saleItem?->getProduct()?->getId(),
                    'product_code' => $saleItem?->getProduct()?->getCode(),
                    'product_name' => $saleItem?->getNameSnapshot() ?: $saleItem?->getProduct()?->getName() ?? '-',
                    'product_uom' => $saleItem
                        ?->getProduct()
                        ?->getUnitOfMeasure()
                        ?->getCode() ?? $saleItem?->getUomSnapshot(),
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'unit_value' => $line->unitValue,
                    'discount_amount' => $line->discountAmount,
                    'taxed_amount' => $line->taxableAmount,
                    'tax_amount' => $line->taxAmount,
                    'tax_percentage' => $taxPercentage,
                    'tax_type' => $taxRegime === TaxRegime::NRUS ? '20' : '10',
                    'total' => $line->totalAmount,
                ]);
            }

            $connection->insert('tax_document_event', [
                'tax_document_id' => $taxDocumentId,
                'event_type' => 'issued',
                'status' => 'pending',
                'message' => 'Comprobante emitido pendiente de envío SUNAT',
            ]);

            $connection->commit();
        } catch (Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }

        $originalTotal = (string) $sale->getTotal();

        $sale->setHasInvoice(true);
        $sale->setInvoice($series . '-' . $nextNumber);
        $sale->setTotal($invoiceAmounts->getTotal());
        if ($requestedVoucherTypeOverride !== null) {
            $sale->setRequestedVoucherType($requestedVoucherTypeOverride);
        }

        $this->syncInvoicedSaleState($saleId, $originalTotal, $requestedVoucherTypeOverride);

        return [
            'success' => true,
            'invoiceId' => $taxDocumentId,
            'message' => 'Comprobante emitido correctamente.',
        ];
    }

    private function syncInvoicedSaleState(
        string $saleId,
        string $total,
        ?RequestedVoucherType $requestedVoucherTypeOverride = null,
    ): void {
        // V2 first: keep new sales table in sync when available.
        if ($this->tableExists('sales')) {
            $setRequestedVoucherType = $requestedVoucherTypeOverride !== null
                ? ', requested_voucher_type = :requested_voucher_type'
                : '';

            $this->connection->executeStatement(
                <<<SQL
                    UPDATE sales
                    SET grand_total = :total,
                        updated_at = UTC_TIMESTAMP(),
                        sync_status = 'SYNCED'
                        {$setRequestedVoucherType}
                    WHERE id = :sale_id
                    SQL,
                [
                    'total' => $total,
                    'sale_id' => $saleId,
                    ...(
                        $requestedVoucherTypeOverride !== null
                            ? ['requested_voucher_type' => $requestedVoucherTypeOverride->value]
                            : []
                    ),
                ],
            );
        }
    }

    private function tableExists(string $tableName): bool
    {
        $exists = $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
                LIMIT 1
                SQL,
            [
                'table_name' => $tableName,
            ],
        );

        return $exists !== false;
    }
}
