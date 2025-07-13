<?php

namespace App\Sale\Domain;

use App\Entity\Sale;
use App\Invoice\Domain\GetOneVoucherUseCase;
use App\Invoice\Domain\InvoiceCreatedEvent;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(string $saleId): ?Voucher
    {
        $sale = $this->entityManager->find(Sale::class, $saleId);

        if (!$sale->isHasInvoice()) {
            $this->invoiceRepository->create($sale);
        }

        $invoice = $this->invoiceRepository->getOneBySaleId($saleId);

        $sale->setHasInvoice(true);
        $sale->setInvoice($invoice->serie . '-' . $invoice->numero);
        $sale->setTotal($invoice->total);
        $this->entityManager->persist($sale);
        $this->entityManager->flush();

        $invoiceCreatedEvent = new InvoiceCreatedEvent($invoice);

        $this->eventDispatcher->dispatch($invoiceCreatedEvent, InvoiceCreatedEvent::NAME);

        return $this->getOneVoucherUseCase->__invoke($saleId);
    }
}
