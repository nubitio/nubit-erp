<?php

declare(strict_types=1);

namespace App\EInvoicing\Adapter;

use App\Core\Context\TenantContext;
use App\Core\Http\ApiResponse;
use App\EInvoicing\Controller\PrintESCPOSController;
use App\EInvoicing\Message\SendInvoiceMessage;
use App\EInvoicing\Sale\SaleVoidUseCase;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\EInvoicing\Service\SaleV2Loader;
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

    public function voidSale(string $saleId, string $reason, int $userId): ApiResponse
    {
        return $this->saleVoidUseCase->__invoke($saleId, $reason, $userId);
    }

    private function loadSale(string $saleId): ?SaleRecord
    {
        return $this->saleV2Loader->loadAsSale($saleId);
    }
}
