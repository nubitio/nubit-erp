<?php

declare(strict_types=1);

namespace App\Billing\Adapter;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\Efact\Http\ApiResponse;
use App\Billing\Controller\PrintESCPOSController;
use App\Billing\Message\SendInvoiceMessage;
use App\Billing\Sale\SaleVoidUseCase;
use App\Billing\Service\InvoiceCreationServiceInterface;
use App\Billing\Service\ReplaceRejectedDocumentService;
use App\Billing\Service\SaleV2Loader;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\SaleInvoicingPortInterface;
use App\Sales\State\DetractionCalculatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class SaleInvoicingAdapter implements SaleInvoicingPortInterface
{
    public function __construct(
        private InvoiceCreationServiceInterface $invoiceCreationService,
        private MessageBusInterface $messageBus,
        private TenantContext $tenantContext,
        private SaleVoidUseCase $saleVoidUseCase,
        private SaleV2Loader $saleV2Loader,
        private DetractionCalculatorInterface $detractionCalculator,
        private PrintESCPOSController $printESCPOSController,
        private ReplaceRejectedDocumentService $replaceRejectedDocumentService,
    ) {
    }

    /**
     * @return array{success: bool, message?: string, invoiceId?: string}
     */
    public function createAndDispatchInvoiceForSaleId(string $saleId): array
    {
        $sale = $this->loadSale($saleId);
        if (!$sale instanceof SaleRecord) {
            return ['success' => false, 'message' => 'La venta no existe.'];
        }

        /** @var array{success: bool, message?: string, invoiceId?: string} $result */
        $result = $this->invoiceCreationService->createForSale($sale);

        if ($result['success'] && isset($result['invoiceId'])) {
            $this->messageBus->dispatch(
                new SendInvoiceMessage(
                    $result['invoiceId'],
                    (string) ($this->tenantContext->getTenantId() ?? 'unknown'),
                ),
                [new DispatchAfterCurrentBusStamp()],
            );
        }

        return $result;
    }

    public function createInvoiceForSaleId(string $saleId): JsonResponse
    {
        $sale = $this->loadSale($saleId);

        if (!$sale instanceof SaleRecord) {
            return ApiResponse::error('La venta no existe.');
        }

        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return ApiResponse::error('La venta ya tiene un comprobante emitido.');
        }

        $this->detractionCalculator->applyFromItems($sale);

        $result = $this->invoiceCreationService->createForSale($sale);

        return $result['success']
            ? ApiResponse::success($result['message'], [
                'detraccionMonto' => $sale->getDetraccionMonto(),
                'detraccionCodigo' => $sale->getDetraccionCodigo(),
            ])
            : ApiResponse::error($result['message']);
    }

    public function printSaleInvoice(string $saleId, Request $request): Response
    {
        return $this->printESCPOSController->__invoke($saleId, $request);
    }

    public function voidSale(string $saleId, string $reason, int $userId, bool $restoreStock = false): ApiResponse
    {
        return $this->saleVoidUseCase->__invoke($saleId, $reason, $userId, $restoreStock);
    }

    public function replaceRejectedDocument(string $saleId): JsonResponse
    {
        $result = $this->replaceRejectedDocumentService->replaceForSaleId($saleId, 'today');

        return $result['success']
            ? ApiResponse::success($result['message'], ['newId' => $result['newId']])
            : ApiResponse::error($result['message']);
    }

    private function loadSale(string $saleId): ?SaleRecord
    {
        return $this->saleV2Loader->loadAsSale($saleId);
    }
}
