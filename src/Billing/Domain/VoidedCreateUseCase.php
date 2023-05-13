<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use App\Billing\Model\Voided\Voided;
use App\Billing\Model\Voided\VoidedDetail;
use App\Invoice\Domain\GetOneVoucherUseCase;

readonly class VoidedCreateUseCase
{
    public function __construct(
        private VoidedRepositoryInterface $voidedRepository,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private BillUtilsInterface $invoiceUtils
    ) {
    }

    public function __invoke(string $saleId, string $reason): DocumentResponse
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);

        $voidedDetail = new VoidedDetail();
        $voidedDetail->tipoDoc = strval($voucher->voucher_type);
        $voidedDetail->serie = strval($voucher->voucher_series);
        $voidedDetail->correlativo = strval($voucher->voucher_number);
        $voidedDetail->desMotivoBaja = strval($voucher->voucher_note);
        $voided = new Voided();
        $lastCorrelative = $this->voidedRepository->getLastCorrelativeByDate(strval($voucher->voucher_date));
        $voided->correlativo = (string) ($lastCorrelative + 1);
        $voided->fecGeneracion = new \DateTime(strval($voucher->voucher_date));
        $voided->fecComunicacion = new \DateTime(strval($voucher->voucher_date));
        $voided->company = $this->invoiceUtils->getCompany();
        $voided->details = [$voidedDetail];
        $response = $this->voidedRepository->voidInvoice($voided);
        $response->name = $voided->getName();

        return $response;
    }
}
