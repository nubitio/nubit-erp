<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

use App\EInvoicing\Shared\ErrorMessages;
use App\Entity\Sale;
use App\Sale\Domain\DocType;
use Brick\Math\Exception\MathException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Throwable;

final readonly class InvoiceCreationService
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     *
     * @throws Exception
     * @throws MathException
     * @throws \DateMalformedStringException
     */
    public function createForSale(Sale $sale): array
    {
        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_ALREADY_HAS_VOUCHER,
            ];
        }

        $saleDate = $sale->getFecha();
        if (!$saleDate) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_HAS_NO_VALID_DATE,
            ];
        }

        $saleDateUtc = (clone $saleDate)->setTimezone(new DateTimeZone('UTC'));
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($saleDateUtc < $nowUtc->modify('-3 days')) {
            return [
                'success' => false,
                'message' => ErrorMessages::SALE_DATE_EXCEEDS_LIMIT,
            ];
        }

        $connection = $this->connection;

        $localId = $connection->fetchOne(
            'select local_id from almacen where almacen_id = ?',
            [$sale->getAlmacen()?->getId()]
        );

        $lines = [];
        foreach ($sale->getItems() as $item) {
            $lines[] = new InvoiceLineInput(
                quantity: (string)$item->getCantidad(),
                unitPrice: $item->getPrecioUnitario() ?? '0'
            );
        }

        $invoiceCalculator = new InvoiceCalculator(
            $lines,
            $sale->getDescuento() ?? '0',
            (string)($sale->getIgv() ?? '0'),
            (string)($sale->getRecargoConsumo() ?? '0'),
            $sale->getTotal() !== null ? (string)$sale->getTotal() : null,
        );
        $invoiceAmounts = $invoiceCalculator->calculate();

        $connection->beginTransaction();
        try {
            $voucherType = strlen(
                $sale->getCliente()?->getRuc() ?? ''
            ) === 11 ? DocType::FACTURA->value : DocType::BOLETA->value;

            $existingInvoice = $connection->fetchOne(
                "SELECT tax_document_id
                 FROM tax_document
                 WHERE movimiento_id = :saleId
                   AND document_type IN (:facturaDocType, :boletaDocType)
                 LIMIT 1 FOR UPDATE",
                [
                    'saleId' => $sale->getId(),
                    'facturaDocType' => DocType::FACTURA->value,
                    'boletaDocType' => DocType::BOLETA->value,
                ]
            );
            if (is_string($existingInvoice) && $existingInvoice !== '') {
                $connection->commit();

                return [
                    'success' => false,
                    'message' => ErrorMessages::SALE_ALREADY_HAS_VOUCHER,
                ];
            }

            $series = $connection->fetchOne(
                'SELECT serie
                        FROM local_comprobante
                        WHERE local_id = :localId
                          AND tipo_comprobante_id = :voucherType
                        LIMIT 1',
                [
                    'localId' => $localId,
                    'voucherType' => $voucherType,
                ]
            );

            if (!$series) {
                $connection->rollBack();

                return [
                    'success' => false,
                    'message' => ErrorMessages::NO_SERIES_CONFIGURED,
                ];
            }

            $nextNumber = $connection->fetchOne(
                'SELECT IFNULL(MAX(CAST(number AS SIGNED)), 0) + 1
                 FROM tax_document
                 WHERE series = :series
                   AND document_type = :voucherType
                 FOR UPDATE',
                [
                    'series' => $series,
                    'voucherType' => $voucherType,
                ]
            );

            $connection->insert('tax_document', [
                'tax_document_id' => $this->connection->fetchOne('SELECT UUID()'),
                'movimiento_id' => $sale->getId(),
                'issue_date' => $saleDateUtc->format('Y-m-d H:i:s'),
                'document_type' => $voucherType,
                'series' => $series,
                'number' => strval($nextNumber),
                'customer_id' => $sale->getCliente()?->getId(),
                'currency' => 'PEN',
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

            $taxDocumentId = $connection->fetchOne(
                "SELECT tax_document_id
                 FROM tax_document
                 WHERE movimiento_id = :saleId
                   AND document_type = :voucherType
                   AND series = :series
                   AND number = :number
                 LIMIT 1",
                [
                    'saleId' => $sale->getId(),
                    'voucherType' => $voucherType,
                    'series' => $series,
                    'number' => strval($nextNumber),
                ]
            );

            $saleItems = array_values($sale->getItems()->toArray());
            $lineNumber = 0;
            foreach ($invoiceAmounts->getLines() as $line) {
                $saleItem = $saleItems[$lineNumber] ?? null;
                $lineNumber++;
                $connection->insert('tax_document_line', [
                    'tax_document_id' => $taxDocumentId,
                    'line_number' => $lineNumber,
                    'product_id' => $saleItem?->getProducto()?->getId(),
                    'product_code' => $saleItem?->getProducto()?->getCode(),
                    'product_name' => $saleItem?->getProducto()?->getName() ?? '-',
                    'product_uom' => $saleItem?->getProducto()?->getUnitOfMeasure()?->getCode(),
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

        $sale->setHasInvoice(true);
        $sale->setInvoice($series . '-' . $nextNumber);
        $sale->setTotal($invoiceAmounts->getTotal());

        $this->connection->executeStatement(
            'UPDATE movimiento SET has_invoice = 1, invoice = ?, total = ? WHERE movimiento_id = ?',
            [$sale->getInvoice(), $sale->getTotal(), $sale->getId()]
        );

        return [
            'success' => true,
            'message' => 'Comprobante emitido correctamente.',
        ];
    }
}
