<?php

declare(strict_types=1);

namespace App\Billing\Controller\Api;

use App\Efact\Http\ApiResponse;
use App\Billing\Service\InvoiceCreationServiceInterface;
use App\Billing\Service\SaleV2Loader;
use App\Sales\State\DetractionCalculatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MakeInvoice extends AbstractController
{
    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Nubit\Platform\Exception\ServiceException
     */
    public function __invoke(
        Request $request,
        InvoiceCreationServiceInterface $invoiceCreationService,
        SaleV2Loader $saleV2Loader,
        DetractionCalculatorInterface $detractionCalculator,
    ): JsonResponse {
        $saleId = $request->get('id');

        // V2 sales table is the authoritative source; no ORM fallback needed.
        $sale = $saleV2Loader->loadAsSale($saleId);

        if (!$sale) {
            return ApiResponse::error('La venta no existe.');
        }

        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return ApiResponse::error('La venta ya tiene un comprobante emitido.');
        }

        $detractionCalculator->applyFromItems($sale);

        $result = $invoiceCreationService->createForSale($sale);

        return $result['success']
            ? ApiResponse::success($result['message'], [
                'detraccionMonto' => $sale->getDetraccionMonto(),
                'detraccionCodigo' => $sale->getDetraccionCodigo(),
            ])
            : ApiResponse::error($result['message']);
    }
}
