<?php

namespace App\Invoice\Domain;

use App\Invoice\Model\Voided\Voided;
use App\Invoice\Model\Voided\VoidedDetail;
use App\Sale\Domain\SaleRepositoryInterface;

class VoidedCreateUseCase
{
    private VoidedRepositoryInterface $voidedRepository;
    private SaleRepositoryInterface $saleRepository;
    private InvoiceUtilsInterface $invoiceUtils;

    public function __construct(
        VoidedRepositoryInterface $voidedRepository,
        SaleRepositoryInterface   $saleRepository,
        InvoiceUtilsInterface     $invoiceUtils
    )
    {
        $this->voidedRepository = $voidedRepository;
        $this->saleRepository = $saleRepository;
        $this->invoiceUtils = $invoiceUtils;
    }

    public function __invoke(string $saleId, string $reason): void
    {
        $sale = $this->saleRepository->getOneById($saleId);
        match ($sale['header']['doc_type']) {
            '01' => $this->voidInvoice($sale),
            '03' => $this->voidTicket($sale),
            default => true,
        };
        dd($sale);
        $this->voidedRepository->insert($voided);
    }

    public function voidInvoice(array $sale): void
    {
        $voidedDetail = new VoidedDetail();
        $voidedDetail->tipoDoc = $sale['header']['doc_type'];
        $voidedDetail->serie = $sale['header']['doc_series'];
        $voidedDetail->correlativo = $sale['header']['doc_number'];
        $voidedDetail->desMotivoBaja = $sale['header']['doc_notes'];
        $voided = new Voided();
        $lastCorrelative = $this->voidedRepository->getLastCorrelativeByDate($sale['header']['doc_date']);
        $voided->correlativo = $lastCorrelative + 1;
        $voided->fecGeneracion = new \DateTime($sale['header']['doc_date']);
        $voided->fecComunicacion = new \DateTime($sale['header']['doc_date']);
        $voided->company = $this->invoiceUtils->getCompany();
        $voided->details = [$voidedDetail];
        $response = $this->voidedRepository->voidInvoice($voided);
        dd($response);
    }

    public function voidTicket(array $sale): void
    {
        $voidedDetail = new VoidedDetail();
        $voidedDetail->tipoDoc = $sale['header']['doc_type'];
        $voidedDetail->serie = $sale['header']['doc_series'];
        $voidedDetail->correlativo = $sale['header']['doc_number'];
        $voidedDetail->desMotivoBaja = $sale['header']['doc_notes'];
        $voided = new Voided();
        $lastCorrelative = $this->voidedRepository->getLastCorrelativeByDate($sale['header']['doc_date']);
        $voided->correlativo = $lastCorrelative + 1;
        $voided->fecGeneracion = new \DateTime($sale['header']['doc_date']);
        $voided->fecComunicacion = new \DateTime($sale['header']['doc_date']);
        $voided->company = $this->invoiceUtils->getCompany();
        $voided->details = [$voidedDetail];
        dd($voided);
    }
}