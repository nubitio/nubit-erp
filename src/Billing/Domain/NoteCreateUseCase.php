<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use NumberFormatter;
use App\Core\Exception\ServiceException;
use App\Entity\CreditNote;
use App\Invoice\Domain\Voucher;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class NoteCreateUseCase
{
    public function __construct(
        private CreditNoteRepository $creditNoteRepository,
        private InvoiceRepository $invoiceRepository,
        private BillUtilsInterface $billUtils,
    ) {
    }

    /**
     * @throws ServiceException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(string $noteId): Note
    {
        $creditNote = $this->creditNoteRepository->find($noteId);
        $voucher = $this->invoiceRepository->getVoucher($creditNote->getSale()->getId());

        return $this->getNote($creditNote, $voucher);
    }

    private function getNote(CreditNote $creditNote, Voucher $voucher): Note
    {
        // Helper para redondear montos a 2 decimales (requerido por SUNAT)
        $round2 = static fn (?float $value): float => round(floatval($value), 2);

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
            ->setFechaEmision($creditNote->getFecha())
            ->setCodMotivo(strval($creditNote->getCodigoMotivo()))
            ->setDesMotivo(strval($creditNote->getDescripcionMotivo()))
            ->setTipoDoc(strval($creditNote->getTipoId()))
            ->setSerie(strval($creditNote->getSerie()))
            ->setCorrelativo(strval($creditNote->getCorrelativo()))
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setCompany($this->billUtils->getCompany())
            ->setClient($client)
            ->setMtoOperGravadas($round2($voucher->voucher_taxed_amount + $voucher->voucher_discount_amount))
            ->setMtoIGV($round2($voucher->voucher_tax_amount))
            ->setTotalImpuestos($round2($voucher->voucher_total_taxes))
            ->setMtoImpVenta($round2($voucher->voucher_sale_amount + $voucher->voucher_discount_amount));

        $items = [];
        foreach ($voucher->voucher_items as $item) {
            $detail = new SaleDetail()
                ->setCodProducto(strval($item->product_code))
                ->setUnidad('NIU')
                ->setCantidad(floatval($item->quantity))
                ->setDescripcion(strval($item->product_name))
                ->setMtoBaseIgv($round2($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv($round2($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos($round2($item->tax_amount))
                ->setMtoValorVenta($round2($item->taxed_amount))
                ->setMtoValorUnitario($round2($item->unit_value))
                ->setMtoPrecioUnitario($round2($item->unit_price));
            $items[] = $detail;
        }

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $parts = explode('.', number_format(floatval($voucher->voucher_sale_amount), 2, '.', ''));
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
