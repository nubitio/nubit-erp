<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use Nubit\Platform\Exception\ServiceException;
use App\Efact\Domain\Money;
use App\Billing\Entity\TaxDocument;
use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Repository\TaxDocumentRepository;
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
        $voucher = $this->taxDocumentRepository->getNoteVoucher($taxDocumentId);

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
        $note->setUblVersion('2.0')
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
            ->setMtoOperGravadas($money($voucher->voucher_taxed_amount ?? 0))
            ->setMtoIGV($money($voucher->voucher_tax_amount ?? 0))
            ->setTotalImpuestos($money($voucher->voucher_total_taxes ?? 0))
            ->setSumOtrosCargos($money($voucher->voucher_consumption_surcharge_amount ?? 0))
            ->setValorVenta(
                $money(
                    Money::of($voucher->voucher_sale_value ?? 0)->minus(
                        Money::of($voucher->voucher_discount_amount ?? 0)
                    )
                )
            )
            ->setSubTotal(
                $money(
                    Money::of($voucher->voucher_sale_amount ?? 0)->minus(
                        Money::of($voucher->voucher_consumption_surcharge_amount ?? 0)
                    )
                )
            )
            ->setMtoImpVenta($money($voucher->voucher_sale_amount ?? 0));

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
        $currencyLabel = ($voucher->voucher_currency ?? 'PEN') === 'USD' ? 'DOLARES' : 'SOLES';
        $strTotal = mb_strtoupper('SON ' . $amount . ' CON ' . $parts[1] . ' / 100 ' . $currencyLabel);

        $legend = new Legend();
        $legend->setCode('1000')
            ->setValue($strTotal);

        $note->setDetails($items)
            ->setLegends([$legend]);

        return $note;
    }
}
