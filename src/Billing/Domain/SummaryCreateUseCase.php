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
    private InvoiceUtilsInterface $invoiceUtils;

    public function __construct(
        SummaryRepositoryInterface $summaryRepository,
        SaleRepositoryInterface    $saleRepository,
        InvoiceUtilsInterface      $invoiceUtils
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
        dd($sale);
        $summaryDetail = new SummaryDetail();
        $summaryDetail->tipoDoc = $sale['header']['doc_type'];
        $summaryDetail->serieNro = $sale['header']['doc_series'] . '-' . $sale['header']['doc_number'];
        $summaryDetail->estado = '3';
        $summaryDetail->clienteTipo = $sale['header']['client_doc_type'];
        $summaryDetail->clienteNro = $sale['header']['client_doc_number'];
        $summaryDetail->total = $sale['header']['doc_total'];
        $summaryDetail->mtoOperGravadas = $sale['header']['doc_total'];
        $summaryDetail->mtoOperInafectas = 0;
        $summaryDetail->mtoOperExoneradas = 0;
        $summaryDetail->mtoOtrosCargos = 0;
        $summaryDetail->mtoIgv = $sale['header']['doc_igv'];
        $summary = new Summary();
        $lastCorrelative = $this->summaryRepository->getLastCorrelativeByDate($sale['header']['doc_date']);
        $summary->correlativo = $lastCorrelative + 1;
        $summary->fecGeneracion = new \DateTime($sale['header']['doc_date']);
        $summary->fecResumen = new \DateTime($sale['header']['doc_date']);
        $summary->company = $this->invoiceUtils->getCompany();
        $summary->details = [$summaryDetail];
        $response = $this->summaryRepository->createSummary($summary);
        $response->name = $summary->getName();

        return $response;
    }
}