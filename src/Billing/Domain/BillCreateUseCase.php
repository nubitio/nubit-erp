<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use DateTime;
use NumberFormatter;
use DateMalformedStringException;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class BillCreateUseCase
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BillUtilsInterface $billUtils,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(string $saleId): Invoice
    {
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $customerAddress = new Address()
            ->setDireccion(strval($voucher->customer_address));

        $customer = new Client()
            ->setTipoDoc(strval($voucher->customer_doc_type))
            ->setNumDoc(strval($voucher->customer_doc_number))
            ->setRznSocial(strval($voucher->customer_name))
            ->setAddress($customerAddress);

        $bill = new Invoice()
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc(strval($voucher->voucher_type))
            ->setSerie(strval($voucher->voucher_series))
            ->setCorrelativo(strval($voucher->voucher_number))
            ->setFechaEmision(new DateTime(strval($voucher->voucher_date)))
            ->setFormaPago(
                2 == $voucher->voucher_payment_method ? new FormaPagoCredito(
                    $voucher->voucher_sale_amount
                ) : new FormaPagoContado()
            )
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setClient($customer)
            ->setMtoOperGravadas(floatval($voucher->voucher_taxed_amount))
            ->setMtoIGV(floatval($voucher->voucher_tax_amount))
            ->setTotalImpuestos(floatval($voucher->voucher_total_taxes))
            ->setSumOtrosCargos(floatval($voucher->voucher_consumption_surcharge_amount ?? null))
            ->setValorVenta(floatval($voucher->voucher_sale_value - $voucher->voucher_discount_amount))
            ->setSubTotal(floatval($voucher->voucher_sale_amount - $voucher->voucher_consumption_surcharge_amount))
            ->setMtoImpVenta(floatval($voucher->voucher_sale_amount))
            ->setCompany($this->billUtils->getCompany());

        if (2 == $voucher->voucher_payment_method) {
            $paymentDate = new DateTime(strval($voucher->voucher_date))->modify('+1 month');

            $bill->setCuotas([
                new Cuota()
                    ->setFechaPago($paymentDate)
                    ->setMonto(floatval($voucher->voucher_sale_amount)),
            ]);
        }

        if (floatval($voucher->voucher_consumption_surcharge_amount) > 0) {
            $charge = new Charge()
                ->setCodTipo('46')
                ->setMontoBase(floatval($voucher->voucher_consumption_surcharge_amount))
                ->setFactor(1)
                ->setMonto(floatval($voucher->voucher_consumption_surcharge_amount));
            $bill->setCargos([$charge]);
        }

        if (floatval($voucher->voucher_discount_amount) > 0) {
            $charge = new Charge()
                ->setCodTipo('02')
                ->setMontoBase(floatval($voucher->voucher_discount_amount))
                ->setFactor(1)
                ->setMonto(floatval($voucher->voucher_discount_amount));
            $bill->setDescuentos([$charge]);
        }

        $details = [];

        foreach ($voucher->voucher_items as $item) {
            $detail = new SaleDetail()
                ->setCodProducto(strval($item->product_code))
                ->setUnidad($item->product_uom ?? 'NIU')
                ->setCantidad(floatval($item->quantity))
                ->setDescripcion(strval($item->product_name))
                ->setMtoValorUnitario(floatval($item->unit_value))
                ->setMtoBaseIgv(floatval($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv(floatval($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos(floatval($item->tax_amount))
                ->setMtoValorVenta(floatval($item->taxed_amount))
                ->setMtoPrecioUnitario(floatval($item->unit_price));
            $details[] = $detail;
        }

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $parts = explode('.', number_format(floatval($voucher->voucher_sale_amount), 2, '.', ''));
        $amount = $formatter->format((int)$parts[0]);
        $strTotal = mb_strtoupper('SON ' . $amount . ' CON ' . $parts[1] . ' / 100 SOLES');

        $legend = new Legend()
            ->setCode('1000')
            ->setValue($strTotal);

        $bill->setDetails($details);
        $bill->setLegends([$legend]);
        $bill->setObservacion(strval($voucher->voucher_note ?? null));

        return $bill;
    }
}
