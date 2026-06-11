<?php

declare(strict_types=1);

namespace App\Sales\Port;

use Nubit\Platform\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface SaleInvoicingPortInterface
{
    /**
     * Creates an invoice for a sale and dispatches asynchronous sending when creation succeeds.
     *
     * @return array{success: bool, message?: string, invoiceId?: string}
     */
    public function createAndDispatchInvoiceForSaleId(string $saleId): array;

    /**
     * Creates an invoice for an existing sale from an explicit API request.
     */
    public function createInvoiceForSaleId(string $saleId): JsonResponse;

    public function printSaleInvoice(string $saleId, Request $request): Response;

    /**
     * @throws \Throwable
     */
    public function voidSale(string $saleId, string $reason, int $userId, bool $restoreStock = false): ApiResponse;

    public function replaceRejectedDocument(string $saleId): JsonResponse;
}
