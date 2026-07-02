<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class VoidedCreateUseCase
{
    public function __construct(
        private VoidedRepositoryInterface $voidedRepository,
        private TaxDocumentRepository $taxDocumentRepository,
        private BillUtilsInterface $invoiceUtils,
    ) {}

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(string $taxDocumentId, string $reason): SummaryResponse
    {
        $voucher = $this->taxDocumentRepository->getVoucherByTaxDocumentId($taxDocumentId);

        $voidedDetail = new VoidedDetail()
            ->setTipoDoc(strval($voucher->voucher_type))
            ->setSerie(strval($voucher->voucher_series))
            ->setCorrelativo(strval($voucher->voucher_number))
            ->setDesMotivoBaja($reason);

        $lastCorrelative = $this->voidedRepository->getLastCorrelativeByDate(strval($voucher->voucher_date));

        $voided = new Voided()
            ->setCorrelativo((string) ($lastCorrelative + 1))
            ->setFecGeneracion(new DateTime(strval($voucher->voucher_date)))
            ->setFecComunicacion(new DateTime(strval($voucher->voucher_date)))
            ->setCompany($this->invoiceUtils->getCompany())
            ->setDetails([$voidedDetail]);
        $response = $this->voidedRepository->voidInvoice($voided);
        $response->name = $voided->getName();

        return $response;
    }
}
