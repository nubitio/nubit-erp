<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Sales\Port\SaleInvoicingPortInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PrintSaleInvoice extends AbstractController
{
    public function __construct(
        private readonly SaleInvoicingPortInterface $saleInvoicing,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        return $this->saleInvoicing->printSaleInvoice($id, $request);
    }
}
