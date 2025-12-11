<?php

namespace App\Billing\Domain;

use DateTime;
use Exception;
use App\Billing\Model\Response\SummaryResponse;
use App\Repository\InvoiceRepository;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class SummaryCreateUseCase
{
    public function __construct(
        private SummaryRepositoryInterface $summaryRepository,
        private InvoiceRepository $invoiceRepository,
        private BillUtilsInterface $invoiceUtils,
    ) {
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(string $saleId): SummaryResponse
    {
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $summaryDetail = new SummaryDetail()
            ->setTipoDoc(strval($voucher->voucher_type))
            ->setSerieNro($voucher->voucher_series . '-' . $voucher->voucher_number)
            ->setEstado('3')
            ->setClienteTipo(strval($voucher->customer_doc_type))
            ->setClienteNro(strval($voucher->customer_doc_number))
            ->setTotal(floatval($voucher->voucher_sale_amount))
            ->setMtoOperGravadas(floatval($voucher->voucher_taxed_amount))
            ->setMtoOperInafectas(0)
            ->setMtoOperExoneradas(0)
            ->setMtoOtrosCargos(0)
            ->setMtoIgv(floatval($voucher->voucher_tax_amount));

        $lastCorrelative = $this->summaryRepository->getLastCorrelativeByDate(strval($voucher->voucher_date));

        $summary = new Summary()
            ->setCorrelativo((string)($lastCorrelative + 1))
            ->setFecGeneracion(new DateTime(strval($voucher->voucher_date)))
            ->setFecResumen(new DateTime(strval($voucher->voucher_date)))
            ->setCompany($this->invoiceUtils->getCompany())
            ->setDetails([$summaryDetail]);
        $response = $this->summaryRepository->createSummary($summary);
        $response->name = $summary->getName();

        return $response;
    }
}
