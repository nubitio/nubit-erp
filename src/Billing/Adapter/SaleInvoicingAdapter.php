<?php

declare(strict_types=1);

namespace App\Billing\Adapter;

use App\Billing\Controller\PrintESCPOSController;
use App\Billing\Message\SendInvoiceMessage;
use App\Billing\Sale\SaleVoidUseCase;
use App\Billing\Service\InvoiceCreationServiceInterface;
use App\Billing\Service\ReplaceRejectedDocumentService;
use App\Billing\Service\SaleV2Loader;
use App\Sales\Entity\SaleRecord;
use App\Sales\Port\InvoiceCreatorInterface;
use App\Sales\Port\RejectedDocumentReplacerInterface;
use App\Sales\Port\Result\InvoiceCreationResult;
use App\Sales\Port\Result\RejectedDocumentReplacementResult;
use App\Sales\Port\SaleInvoicePrinterInterface;
use App\Sales\Port\SaleVoiderInterface;
use App\Sales\State\DetractionCalculatorInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class SaleInvoicingAdapter implements
    InvoiceCreatorInterface,
    SaleInvoicePrinterInterface,
    SaleVoiderInterface,
    RejectedDocumentReplacerInterface
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
    ) {}

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

    public function createInvoiceForSaleId(string $saleId): InvoiceCreationResult
    {
        $sale = $this->loadSale($saleId);

        if (!$sale instanceof SaleRecord) {
            return InvoiceCreationResult::failure('La venta no existe.');
        }

        if ($sale->isHasInvoice() && $sale->getInvoice() !== null) {
            return InvoiceCreationResult::failure('La venta ya tiene un comprobante emitido.');
        }

        $this->detractionCalculator->applyFromItems($sale);

        $result = $this->invoiceCreationService->createForSale($sale);

        return $result['success']
            ? InvoiceCreationResult::success(
                $result['message'],
                $sale->getDetraccionMonto(),
                $sale->getDetraccionCodigo(),
            )
            : InvoiceCreationResult::failure($result['message']);
    }

    public function printSaleInvoice(string $saleId, Request $request): Response
    {
        return $this->printESCPOSController->__invoke($saleId, $request);
    }

    public function voidSale(string $saleId, string $reason, int $userId, bool $restoreStock = false): void
    {
        $this->saleVoidUseCase->__invoke($saleId, $reason, $userId, $restoreStock);
    }

    public function replaceRejectedDocument(string $saleId): RejectedDocumentReplacementResult
    {
        $result = $this->replaceRejectedDocumentService->replaceForSaleId($saleId, 'today');

        return $result['success']
            ? RejectedDocumentReplacementResult::success($result['message'], $result['newId'] ?? null)
            : RejectedDocumentReplacementResult::failure($result['message']);
    }

    private function loadSale(string $saleId): ?SaleRecord
    {
        return $this->saleV2Loader->loadAsSale($saleId);
    }
}
