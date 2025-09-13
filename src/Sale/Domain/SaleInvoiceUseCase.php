<?php

namespace App\Sale\Domain;

use App\Entity\Sale;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use Doctrine\ORM\EntityManagerInterface;

readonly class SaleInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
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

        return $this->invoiceRepository->getVoucher($saleId);
    }
}
