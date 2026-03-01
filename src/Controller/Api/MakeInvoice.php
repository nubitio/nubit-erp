<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\EInvoicing\Service\InvoiceCreationService;
use App\EInvoicing\Service\SaleV2Loader;
use App\Repository\SaleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MakeInvoice extends AbstractController
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function __invoke(
        Request $request,
        SaleRepository $saleRepository,
        InvoiceCreationService $invoiceCreationService,
        SaleV2Loader $saleV2Loader,
    ): JsonResponse {
        $saleId = $request->get('id');

        // Try legacy movimiento table first; fall back to V2 sales table.
        $sale = $saleRepository->find($saleId) ?? $saleV2Loader->loadAsSale($saleId);

        if (!$sale) {
            return ApiResponse::error('La venta no existe.');
        }

        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return ApiResponse::error('La venta ya tiene un comprobante emitido.');
        }

        $result = $invoiceCreationService->createForSale($sale);

        return $result['success']
            ? ApiResponse::success($result['message'])
            : ApiResponse::error($result['message']);
    }
}
