<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Sale\Domain\SaleRepositoryInterface;

class InvoiceCreateUseCase
{
    private InvoiceRepositoryInterface $invoiceRepository;
    private SaleRepositoryInterface $saleRepository;
    private InvoiceUtilsInterface $invoiceUtils;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        SaleRepositoryInterface   $saleRepository,
        InvoiceUtilsInterface     $invoiceUtils
    )
    {
        $this->invoiceRepository = $invoiceRepository;
        $this->saleRepository = $saleRepository;
        $this->invoiceUtils = $invoiceUtils;
    }

    public function __invoke(string $saleId): DocumentResponse
    {
        $sale = $this->saleRepository->getOneById($saleId);
        dd($sale);
        
    }
}