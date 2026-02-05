<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Core\Exception\ServiceException;
use App\Core\Util\Money;
use App\Entity\TaxDocument;
use App\Invoice\Domain\Voucher;
use App\Repository\TaxDocumentRepository;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use NumberFormatter;

readonly class NoteCreateUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private BillUtilsInterface $billUtils,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws MathException
     */
    public function __invoke(string $taxDocumentId): Note
    {
        $creditNote = $this->taxDocumentRepository->find($taxDocumentId);
        $voucher = $this->taxDocumentRepository->getVoucher($creditNote->getSale()->getId());

        return $this->getNote($creditNote, $voucher);
    }

    /**
     * @throws MathException
     */
    private function getNote(TaxDocument $creditNote, Voucher $voucher): Note
    {
        $money = static fn (mixed $value): float => Money::toFloat(Money::money($value));

        $customerAddress = new Address();
        $customerAddress->setDireccion(strval($voucher->customer_address));

        $client = new Client();
        $client->setTipoDoc(strval($voucher->customer_doc_type))
            ->setNumDoc(strval($voucher->customer_doc_number))
            ->setRznSocial(strval($voucher->customer_name))
            ->setAddress($customerAddress);

        $note = new Note();
        $note->setUblVersion('2.1')
            ->setTipDocAfectado($voucher->voucher_type)
            ->setNumDocfectado($voucher->voucher_series . '-' . $voucher->voucher_number)
            ->setFechaEmision($creditNote->getIssueDate())
            ->setCodMotivo($creditNote->getReasonCode())
            ->setDesMotivo($creditNote->getReasonDescription())
            ->setTipoDoc($creditNote->getDocumentType())
            ->setSerie($creditNote->getSeries())
            ->setCorrelativo($creditNote->getNumber())
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setCompany($this->billUtils->getCompany())
            ->setClient($client)
            ->setMtoOperGravadas(
                $money(Money::of($voucher->voucher_taxed_amount)->plus(Money::of($voucher->voucher_discount_amount)))
            )
            ->setMtoIGV($money($voucher->voucher_tax_amount))
            ->setTotalImpuestos($money($voucher->voucher_total_taxes))
            ->setMtoImpVenta(
                $money(Money::of($voucher->voucher_sale_amount)->plus(Money::of($voucher->voucher_discount_amount)))
            );

        $items = [];
        foreach ($voucher->voucher_items as $item) {
            $detail = new SaleDetail()
                ->setCodProducto(strval($item->product_code))
                ->setUnidad('NIU')
                ->setCantidad(floatval($item->quantity))
                ->setDescripcion(strval($item->product_name))
                ->setMtoBaseIgv($money($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv($money($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos($money($item->tax_amount))
                ->setMtoValorVenta($money($item->taxed_amount))
                ->setMtoValorUnitario($money($item->unit_value))
                ->setMtoPrecioUnitario($money($item->unit_price));
            $items[] = $detail;
        }

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $amountString = Money::toString(Money::money($voucher->voucher_sale_amount));
        $parts = explode('.', $amountString);
        $amount = $formatter->format((int)$parts[0]);
        $strTotal = mb_strtoupper('SON ' . $amount . ' CON ' . $parts[1] . ' / 100 SOLES');

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue($strTotal);

        $note->setDetails($items)
            ->setLegends([$legend]);

        return $note;
    }
}
