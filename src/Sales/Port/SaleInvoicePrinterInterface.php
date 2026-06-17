<?php

declare(strict_types=1);

namespace App\Sales\Port;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders printable representations (e.g. ESC/POS ticket) of a sale invoice.
 */
interface SaleInvoicePrinterInterface
{
    public function printSaleInvoice(string $saleId, Request $request): Response;
}
