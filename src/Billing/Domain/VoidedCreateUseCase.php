<?php

namespace App\Billing\Domain;

use DateTime;
use DateMalformedStringException;
use App\Billing\Model\Response\SummaryResponse;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;

readonly class VoidedCreateUseCase
{
    public function __construct(
        private VoidedRepositoryInterface $voidedRepository,
        private InvoiceRepository $invoiceRepository,
        private BillUtilsInterface $invoiceUtils,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function __invoke(string $saleId, string $reason): SummaryResponse
    {
        $voucher = $this->invoiceRepository->getVoucher($saleId);

        $voidedDetail = new VoidedDetail()
            ->setTipoDoc(strval($voucher->voucher_type))
            ->setSerie(strval($voucher->voucher_series))
            ->setCorrelativo(strval($voucher->voucher_number))
            ->setDesMotivoBaja($reason);

        $lastCorrelative = $this->voidedRepository->getLastCorrelativeByDate(strval($voucher->voucher_date));

        $voided = new Voided()
            ->setCorrelativo((string)($lastCorrelative + 1))
            ->setFecGeneracion(new DateTime(strval($voucher->voucher_date)))
            ->setFecComunicacion(new DateTime(strval($voucher->voucher_date)))
            ->setCompany($this->invoiceUtils->getCompany())
            ->setDetails([$voidedDetail]);
        $response = $this->voidedRepository->voidInvoice($voided);
        $response->name = $voided->getName();

        return $response;
    }
}
