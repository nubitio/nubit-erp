<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Shared\ErrorMessages;
use App\Catalog\Entity\UnitOfMeasure;
use App\Sales\Domain\DocType;
use App\Sales\Entity\SaleRecord;
use App\Sales\Service\SaleCurrencyResolver;
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
    ) {}

    /**
     * @return array{success: bool, message: string, invoiceId?: string}
     *
     * @throws Exception
     * @throws MathException
     * @throws \DateMalformedStringException
     */
    public function createForSale(SaleRecord $sale): array
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

        $invoiceCalculator = new InvoiceCalculator(
            $lines,
            $sale->getDescuento() ?? '0',
            (string) ($sale->getIgv() ?? '0'),
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
        $v2SaleId = $connection->fetchOne('SELECT id FROM sales WHERE id = :id OR legacy_movimiento_id = :id LIMIT 1', [
            'id' => $saleId,
        ]);
        $resolvedV2SaleId = is_string($v2SaleId) && $v2SaleId !== '' ? $v2SaleId : null;

        $connection->beginTransaction();
        try {
            $voucherType = strlen($sale->getCliente()?->getRuc() ?? '') === 11
                ? DocType::FACTURA->value
                : DocType::BOLETA->value;

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
                    'tax_percentage' => $sale->getIgv() ?? 0,
                    'tax_type' => '10',
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

        $this->syncInvoicedSaleState($saleId, $originalTotal);

        return [
            'success' => true,
            'invoiceId' => $taxDocumentId,
            'message' => 'Comprobante emitido correctamente.',
        ];
    }

    private function syncInvoicedSaleState(string $saleId, string $total): void
    {
        // V2 first: keep new sales table in sync when available.
        if ($this->tableExists('sales')) {
            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE sales
                    SET grand_total = :total,
                        paid_total = :total,
                        balance_due = 0,
                        updated_at = UTC_TIMESTAMP(),
                        sync_status = 'SYNCED'
                    WHERE id = :sale_id OR legacy_movimiento_id = :sale_id
                    SQL,
                [
                    'total' => $total,
                    'sale_id' => $saleId,
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
