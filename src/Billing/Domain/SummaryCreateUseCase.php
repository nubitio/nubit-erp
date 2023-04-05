<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Summary\Summary;
use App\Billing\Model\Summary\SummaryDetail;
use App\Sale\Domain\SaleRepositoryInterface;

class SummaryCreateUseCase
{
    private SummaryRepositoryInterface $summaryRepository;
    private SaleRepositoryInterface $saleRepository;
    private BillUtilsInterface $invoiceUtils;

    public function __construct(
        SummaryRepositoryInterface $summaryRepository,
        SaleRepositoryInterface    $saleRepository,
        BillUtilsInterface         $invoiceUtils
    )
    {
        $this->summaryRepository = $summaryRepository;
        $this->saleRepository = $saleRepository;
        $this->invoiceUtils = $invoiceUtils;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId): DocumentResponse
    {
        $sale = $this->saleRepository->getOneById($saleId);
        $summaryDetail = new SummaryDetail();
        $summaryDetail->tipoDoc = strval($sale['header']['doc_type']);
        $summaryDetail->serieNro = $sale['header']['doc_series'] . '-' . $sale['header']['doc_number'];
        $summaryDetail->estado = '3';
        $summaryDetail->clienteTipo = strval($sale['header']['client_doc_type']);
        $summaryDetail->clienteNro = strval($sale['header']['client_doc_number']);
        $summaryDetail->total = floatval($sale['header']['doc_sale_amount']);
        $summaryDetail->mtoOperGravadas = floatval($sale['header']['doc_taxed_amount']);
        $summaryDetail->mtoOperInafectas = 0;
        $summaryDetail->mtoOperExoneradas = 0;
        $summaryDetail->mtoOtrosCargos = 0;
        $summaryDetail->mtoIgv = floatval($sale['header']['doc_igv_amount']);
        $summary = new Summary();
        $lastCorrelative = $this->summaryRepository->getLastCorrelativeByDate(strval($sale['header']['doc_date']));
        $summary->correlativo = (string)($lastCorrelative + 1);
        $summary->fecGeneracion = new \DateTime(strval($sale['header']['doc_date']));
        $summary->fecResumen = new \DateTime(strval($sale['header']['doc_date']));
        $summary->company = $this->invoiceUtils->getCompany();
        $summary->details = [$summaryDetail];
        $response = $this->summaryRepository->createSummary($summary);
        $response->name = $summary->getName();

        return $response;
    }
}
