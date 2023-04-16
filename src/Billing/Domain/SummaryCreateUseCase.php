<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Summary\Summary;
use App\Billing\Model\Summary\SummaryDetail;
use App\Invoice\Domain\GetOneVoucherUseCase;

readonly class SummaryCreateUseCase
{

    public function __construct(
        private SummaryRepositoryInterface $summaryRepository,
        private GetOneVoucherUseCase       $getOneVoucherUseCase,
        private BillUtilsInterface         $invoiceUtils
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(string $saleId): DocumentResponse
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $summaryDetail = new SummaryDetail();
        $summaryDetail->tipoDoc = strval($voucher->voucher_type);
        $summaryDetail->serieNro = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $summaryDetail->estado = '3';
        $summaryDetail->clienteTipo = strval($voucher->customer_doc_type);
        $summaryDetail->clienteNro = strval($voucher->customer_doc_number);
        $summaryDetail->total = floatval($voucher->voucher_sale_amount);
        $summaryDetail->mtoOperGravadas = floatval($voucher->voucher_taxed_amount);
        $summaryDetail->mtoOperInafectas = 0;
        $summaryDetail->mtoOperExoneradas = 0;
        $summaryDetail->mtoOtrosCargos = 0;
        $summaryDetail->mtoIgv = floatval($voucher->voucher_tax_amount);
        $summary = new Summary();
        $lastCorrelative = $this->summaryRepository->getLastCorrelativeByDate(strval($voucher->voucher_date));
        $summary->correlativo = (string)($lastCorrelative + 1);
        $summary->fecGeneracion = new \DateTime(strval($voucher->voucher_date));
        $summary->fecResumen = new \DateTime(strval($voucher->voucher_date));
        $summary->company = $this->invoiceUtils->getCompany();
        $summary->details = [$summaryDetail];
        $response = $this->summaryRepository->createSummary($summary);
        $response->name = $summary->getName();

        return $response;
    }
}
