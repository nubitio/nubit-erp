<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CreditNote;
use App\Entity\Invoice;
use App\Invoice\Domain\Voucher;
use App\Invoice\Domain\VoucherItem;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Uid\Uuid;

final readonly class TaxDocumentSyncService
{
    private const array INVOICE_TYPES = ['01', '03'];

    public function __construct(
        private Connection $connection,
        private InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function syncInvoice(Invoice $invoice): void
    {
        $saleId = $invoice->getSaleId();
        $voucher = $this->invoiceRepository->getVoucher($saleId);

        $taxDocumentId = $this->upsertTaxDocument(
            movementId: $saleId,
            documentType: $invoice->getVoucherTypeId(),
            series: $invoice->getSeries(),
            number: $invoice->getNumber(),
            data: [
                'related_tax_document_id' => null,
                'branch_id' => $invoice->getBranch()?->getId(),
                'customer_id' => $invoice->getCustomer()?->getRuc(),
                'document_type' => $invoice->getVoucherTypeId(),
                'issue_date' => $invoice->getIssuedAt()->format('Y-m-d H:i:s'),
                'series' => $invoice->getSeries(),
                'number' => $invoice->getNumber(),
                'affected_document' => null,
                'reason_code' => null,
                'reason_description' => null,
                'currency' => strval($voucher->voucher_currency ?: 'PEN'),
                'payment_method' => $invoice->getSale()->getFormaPago(),
                'subtotal' => $invoice->getSubTotal(),
                'taxable_amount' => $invoice->getTaxableAmount(),
                'igv_amount' => $invoice->getIgv(),
                'rc_amount' => $invoice->getRc(),
                'total' => $invoice->getTotal(),
                'discount_percent' => (string)$invoice->getDiscountPercent(),
                'discount_amount' => $invoice->getDiscountAmount(),
                'operation_type' => $invoice->getOperationType(),
                'detraction_code' => $invoice->getDetractionCode(),
                'detraction_percent' => $invoice->getDetractionPercent(),
                'detraction_amount' => $invoice->getDetractionAmount(),
                'detraction_payment_method' => $invoice->getDetractionPaymentMethod(),
                'detraction_value_ref' => $invoice->getDetractionValueRef(),
                'sunat_status' => $invoice->getSunatStatus(),
                'sunat_message' => $invoice->getSunatMessage(),
                'digest' => $invoice->getDigest(),
                'void_ticket' => $invoice->getVoidTicket(),
                'void_correlation' => $invoice->getVoidCorrelation(),
                'sent_at' => null,
                'accepted_at' => null,
                'created_at' => $invoice->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $invoice->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]
        );

        $this->replaceLinesFromVoucher($taxDocumentId, $voucher);
    }

    /**
     * @throws Exception
     */
    public function syncCreditNote(CreditNote $creditNote): void
    {
        $saleId = strval($creditNote->getSale()->getId());
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $relatedDocumentId = $this->findTaxDocumentByMovementAndTypes(
            $saleId,
            self::INVOICE_TYPES
        );

        $taxDocumentId = $this->upsertTaxDocument(
            movementId: $saleId,
            documentType: $creditNote->getTipoId(),
            series: $creditNote->getSerie(),
            number: strval($creditNote->getCorrelativo()),
            data: [
                'related_tax_document_id' => $relatedDocumentId,
                'branch_id' => $creditNote->getBranch()?->getId(),
                'customer_id' => $creditNote->getSale()->getCliente()?->getRuc(),
                'document_type' => $creditNote->getTipoId(),
                'issue_date' => $creditNote->getFecha()->format('Y-m-d H:i:s'),
                'series' => $creditNote->getSerie(),
                'number' => strval($creditNote->getCorrelativo()),
                'affected_document' => $creditNote->getDocAfectado(),
                'reason_code' => $creditNote->getCodigoMotivo(),
                'reason_description' => $creditNote->getDescripcionMotivo(),
                'currency' => strval($voucher->voucher_currency ?: 'PEN'),
                'payment_method' => $creditNote->getSale()->getFormaPago(),
                'subtotal' => strval($creditNote->getTotal()),
                'taxable_amount' => strval($voucher->voucher_taxed_amount ?? $creditNote->getTotal()),
                'igv_amount' => strval($voucher->voucher_tax_amount ?? '0.00'),
                'rc_amount' => strval($voucher->voucher_consumption_surcharge_amount ?? '0.00'),
                'total' => strval($creditNote->getTotal()),
                'discount_percent' => strval($voucher->voucher_discount_percentage ?? '0'),
                'discount_amount' => strval($voucher->voucher_discount_amount ?? '0.00'),
                'operation_type' => $voucher->voucher_operation_type,
                'detraction_code' => $voucher->voucher_detraction_code,
                'detraction_percent' => $voucher->voucher_detraction_percent,
                'detraction_amount' => $voucher->voucher_detraction_amount,
                'detraction_payment_method' => $voucher->voucher_detraction_payment_method,
                'detraction_value_ref' => $voucher->voucher_detraction_value_ref,
                'sunat_status' => $creditNote->getEnvioSunat(),
                'sunat_message' => $creditNote->getMensajeSunat(),
                'digest' => $creditNote->getDigest(),
                'void_ticket' => null,
                'void_correlation' => null,
                'sent_at' => null,
                'accepted_at' => null,
                'created_at' => $creditNote->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $creditNote->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]
        );

        $this->replaceLinesFromVoucher($taxDocumentId, $voucher);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @throws Exception
     */
    public function recordSunatEvent(
        string $documentType,
        string $series,
        string $number,
        string $eventType,
        ?string $statusCode,
        ?string $statusMessage,
        ?string $digest = null,
        ?array $payload = null,
    ): void {
        $taxDocumentId = $this->findTaxDocumentByDocument(
            $documentType,
            $series,
            $number
        );
        if ($taxDocumentId === null) {
            return;
        }

        $normalizedCode = strtolower(trim((string)$statusCode));
        $accepted = in_array($normalizedCode, ['0', 'aceptado', 'accepted'], true);
        $pending = $normalizedCode === '' || $normalizedCode === 'pendiente';

        $this->connection->update('tax_document', [
            'sunat_status' => $statusCode,
            'sunat_message' => $statusMessage,
            'digest' => $digest,
            'sent_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'accepted_at' => $accepted ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null,
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'tax_document_id' => $taxDocumentId,
        ]);

        $this->connection->insert('tax_document_event', [
            'tax_document_id' => $taxDocumentId,
            'event_type' => $eventType,
            'status' => $pending ? 'pending' : ($accepted ? 'accepted' : 'error'),
            'code' => $statusCode,
            'message' => $statusMessage,
            'payload_json' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws Exception
     */
    private function upsertTaxDocument(
        string $movementId,
        string $documentType,
        string $series,
        string $number,
        array $data,
    ): string {
        $taxDocumentId = $this->findTaxDocumentByDocument($documentType, $series, $number);
        if ($taxDocumentId === null) {
            $taxDocumentId = Uuid::v4()->toRfc4122();
            $this->connection->insert('tax_document', array_merge($data, [
                'tax_document_id' => $taxDocumentId,
                'movimiento_id' => $movementId,
            ]));

            return $taxDocumentId;
        }

        $updateData = array_merge($data, [
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        unset($updateData['created_at']);
        if (($updateData['sent_at'] ?? null) === null) {
            unset($updateData['sent_at']);
        }
        if (($updateData['accepted_at'] ?? null) === null) {
            unset($updateData['accepted_at']);
        }

        $this->connection->update('tax_document', $updateData, [
            'tax_document_id' => $taxDocumentId,
        ]);

        return $taxDocumentId;
    }

    /**
     * @throws Exception
     */
    private function replaceLinesFromVoucher(string $taxDocumentId, Voucher $voucher): void
    {
        $this->connection->delete('tax_document_line', ['tax_document_id' => $taxDocumentId]);

        foreach ($voucher->voucher_items as $index => $item) {
            $lineNumber = $index + 1;
            $this->insertLine($taxDocumentId, $lineNumber, $item);
        }
    }

    /**
     * @throws Exception
     */
    private function insertLine(string $taxDocumentId, int $lineNumber, VoucherItem $item): void
    {
        $productName = trim(strval($item->product_name));
        $this->connection->insert('tax_document_line', [
            'tax_document_id' => $taxDocumentId,
            'line_number' => $lineNumber,
            'product_id' => $item->product_id,
            'product_code' => $item->product_code,
            'product_name' => $productName === '' ? '-' : $productName,
            'product_uom' => $item->product_uom,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'unit_value' => $item->unit_value,
            'discount_amount' => $item->discount_amount,
            'taxed_amount' => $item->taxed_amount,
            'tax_amount' => $item->tax_amount,
            'tax_percentage' => $item->tax_percentage,
            'tax_type' => $item->tax_type,
            'total' => $item->total,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @throws Exception
     */
    private function findTaxDocumentByDocument(string $documentType, string $series, string $number): ?string
    {
        $taxDocumentId = $this->connection->fetchOne(
            'SELECT tax_document_id
             FROM tax_document
             WHERE document_type = :documentType
               AND series = :series
               AND number = :number
             LIMIT 1',
            [
                'documentType' => $documentType,
                'series' => $series,
                'number' => $number,
            ]
        );

        return is_string($taxDocumentId) ? $taxDocumentId : null;
    }

    private function findTaxDocumentByMovementAndTypes(string $movementId, array $types): ?string
    {
        if ($types === []) {
            return null;
        }

        $params = ['movementId' => $movementId];
        $placeholders = [];
        foreach ($types as $index => $type) {
            $key = 'type' . $index;
            $params[$key] = $type;
            $placeholders[] = ':' . $key;
        }

        $sql = sprintf(
            'SELECT tax_document_id
             FROM tax_document
             WHERE movimiento_id = :movementId
               AND document_type IN (%s)
             ORDER BY created_at DESC
             LIMIT 1',
            implode(', ', $placeholders)
        );

        $taxDocumentId = $this->connection->fetchOne($sql, $params);

        return is_string($taxDocumentId) ? $taxDocumentId : null;
    }
}
