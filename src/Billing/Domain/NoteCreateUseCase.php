<?php

namespace App\Billing\Domain;

use App\Core\Domain\Exception\ServiceException;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Voucher;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;

readonly class NoteCreateUseCase
{
    public function __construct(
        private GetOneCreditNoteUseCase $getOneCreditNoteUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private BillUtilsInterface $billUtils,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(string $noteId): Note
    {
        $creditNote = $this->getOneCreditNoteUseCase->__invoke($noteId);
        if (null !== $creditNote->sale?->getId()) {
            $voucher = $this->invoiceRepository->getVoucher($creditNote->sale->getId());
        }

        if (!isset($voucher)) {
            throw new ServiceException('No se encontró el comprobante asociado a la nota de crédito.');
        }

        return $this->getNote($creditNote, $voucher);
    }

    private function getNote(CreditNote $creditNote, Voucher $voucher): Note
    {
        $customerAddress = new Address();
        $customerAddress->setDireccion(strval($voucher->customer_address));

        $client = new Client();
        $client->setTipoDoc(strval($voucher->customer_doc_type))
            ->setNumDoc(strval($voucher->customer_doc_number))
            ->setRznSocial(strval($voucher->customer_name))
            ->setAddress($customerAddress);

        $note = new Note();
        // TODO: review setMtoOperGravadas and setMtoImpVenta
        $note->setUblVersion('2.1')
            ->setTipDocAfectado($voucher->voucher_type)
            ->setNumDocfectado($voucher->voucher_series . '-' . $voucher->voucher_number)
            ->setFechaEmision($creditNote->fecha)
            ->setCodMotivo(strval($creditNote->codigo_motivo))
            ->setDesMotivo(strval($creditNote->descripcion_motivo))
            ->setTipoDoc(strval($creditNote->tipo_id))
            ->setSerie(strval($creditNote->serie))
            ->setCorrelativo(strval($creditNote->correlativo))
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setCompany($this->billUtils->getCompany())
            ->setClient($client)
            ->setMtoOperGravadas(floatval($voucher->voucher_taxed_amount) + floatval($voucher->voucher_discount_amount))
            ->setMtoIGV(floatval($voucher->voucher_tax_amount))
            ->setTotalImpuestos(floatval($voucher->voucher_total_taxes))
            ->setMtoImpVenta(floatval($voucher->voucher_sale_amount) + floatval($voucher->voucher_discount_amount));

        $items = [];
        foreach ($voucher->voucher_items as $item) {
            $item = (new SaleDetail())
                ->setCodProducto(strval($item->product_code))
                ->setUnidad('NIU')
                ->setCantidad(floatval($item->quantity))
                ->setDescripcion(strval($item->product_name))
                ->setMtoBaseIgv(floatval($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv(floatval($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos(floatval($item->tax_amount))
                ->setMtoValorVenta(floatval($item->taxed_amount))
                ->setMtoValorUnitario(floatval($item->unit_value))
                ->setMtoPrecioUnitario(floatval($item->unit_price));
            $items[] = $item;
        }

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
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
