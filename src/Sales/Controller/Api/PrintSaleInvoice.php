<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Sales\Port\SaleInvoicePrinterInterface;
use App\Sales\Security\SaleReadAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PrintSaleInvoice extends AbstractController
{
    public function __construct(
        private readonly SaleInvoicePrinterInterface $saleInvoicePrinter,
        private readonly SaleReadAccess $access,
    ) {}

    public function __invoke(string $id, Request $request): Response
    {
        $this->access->assertCanReadSale($id);

        return $this->saleInvoicePrinter->printSaleInvoice($id, $request);
    }
}
