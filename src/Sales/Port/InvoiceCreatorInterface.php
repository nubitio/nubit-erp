<?php

declare(strict_types=1);

namespace App\Sales\Port;

use App\Sales\Domain\RequestedVoucherType;
use App\Sales\Port\Result\InvoiceCreationResult;

/**
 * Creates electronic invoices for existing sales.
 */
interface InvoiceCreatorInterface
{
    /**
     * Creates an invoice for a sale and dispatches asynchronous sending when creation succeeds.
     *
     * Used by the sale processing pipeline (fire-and-forget style).
     *
     * @return array{success: bool, message?: string, invoiceId?: string}
     */
    public function createAndDispatchInvoiceForSaleId(string $saleId): array;

    /**
     * Creates an invoice for an existing sale from an explicit request.
     *
     * @param RequestedVoucherType|null $requestedVoucherType when given, overrides and persists
     *        the sale's requested voucher type before invoicing (e.g. the Ventas module letting a
     *        user pick Boleta/Factura at invoice time instead of at sale creation).
     */
    public function createInvoiceForSaleId(
        string $saleId,
        ?RequestedVoucherType $requestedVoucherType = null,
    ): InvoiceCreationResult;
}
