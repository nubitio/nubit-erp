<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Voided\Voided;
use App\Billing\Model\Voided\VoidedDetail;
use App\Sale\Domain\SaleRepositoryInterface;

class VoidedCreateUseCase
{
    private VoidedRepositoryInterface $voidedRepository;
    private SaleRepositoryInterface $saleRepository;
    private BillUtilsInterface $invoiceUtils;

    public function __construct(
        VoidedRepositoryInterface $voidedRepository,
        SaleRepositoryInterface $saleRepository,
        BillUtilsInterface $invoiceUtils
    ) {
        $this->voidedRepository = $voidedRepository;
        $this->saleRepository = $saleRepository;
        $this->invoiceUtils = $invoiceUtils;
    }

    public function __invoke(string $saleId, string $reason): DocumentResponse
    {
        $sale = $this->saleRepository->getOneById($saleId);

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
        $response->name = $voided->getName();

        return $response;
    }
}
