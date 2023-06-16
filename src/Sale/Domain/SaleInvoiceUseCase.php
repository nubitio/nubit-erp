<?php

namespace App\Sale\Domain;

use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceCreatedEvent;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private GetOneSaleUseCase $getOneSaleUseCase,
        private UpdateSaleUseCase $updateSaleUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(string $saleId): ?Voucher
    {
        $sale = $this->getOneSaleUseCase->__invoke($saleId);

        if (!$sale->has_invoice) {
            $this->invoiceRepository->create($sale);
        }

        $invoice = $this->invoiceRepository->getOne($saleId);

        $sale->has_invoice = true;
        $sale->invoice = $invoice->serie.'-'.$invoice->numero;
        $sale->total = $invoice->total;
        $this->updateSaleUseCase->__invoke($sale);

        $invoiceCreatedEvent = new InvoiceCreatedEvent($invoice);

        $this->eventDispatcher->dispatch($invoiceCreatedEvent, InvoiceCreatedEvent::NAME);

        return $this->getOneVoucherUseCase->__invoke($saleId);
    }
}
