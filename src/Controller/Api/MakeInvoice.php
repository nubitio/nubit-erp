<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Http\ApiResponse;
use App\Repository\SaleRepository;
use App\EInvoicing\Service\InvoiceCreationService;
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
    ): JsonResponse {
        $saleId = $request->get('id');
        $sale = $saleRepository->find($saleId);

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
