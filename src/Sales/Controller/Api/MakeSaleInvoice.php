<?php

declare(strict_types=1);

namespace App\Sales\Controller\Api;

use App\Sales\Port\InvoiceCreatorInterface;
use Nubit\Platform\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MakeSaleInvoice extends AbstractController
{
    public function __construct(
        private readonly InvoiceCreatorInterface $invoiceCreator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->invoiceCreator->createInvoiceForSaleId((string) $request->get('id'));

        if (!$result->success) {
            return ApiResponse::error($result->message);
        }

        return ApiResponse::success($result->message, [
            'detraccionMonto' => $result->detraccionMonto,
            'detraccionCodigo' => $result->detraccionCodigo,
        ]);
    }
}
