<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Sales\Port\SaleInvoicingPortInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReplaceRejectedSaleDocument extends AbstractController
{
    public function __construct(
        private readonly SaleInvoicingPortInterface $invoicingPort,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $saleId = (string) $request->get('id');

        return $this->invoicingPort->replaceRejectedDocument($saleId);
    }
}
