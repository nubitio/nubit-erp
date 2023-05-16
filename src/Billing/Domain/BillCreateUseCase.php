<?php

namespace App\Billing\Domain;

use App\Billing\Model\Address\Address;
use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Bill\BillDetail;
use App\Billing\Model\Bill\Charge;
use App\Billing\Model\Bill\Legend;
use App\Billing\Model\Bill\PagoContado;
use App\Billing\Model\Client\Client;
use App\Invoice\Domain\GetOneVoucherUseCase;

readonly class BillCreateUseCase
{
    public function __construct(
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private BillUtilsInterface $billUtils,
    ) {
    }

    public function __invoke(string $saleId): Bill
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);
        $customerAddress = new Address();
        $customerAddress->direccion = strval($voucher->customer_address);

        $customer = new Client();
        $customer->tipoDoc = strval($voucher->customer_doc_type);
        $customer->numDoc = strval($voucher->customer_doc_number);
        $customer->rznSocial = strval($voucher->customer_name);
        $customer->address = $customerAddress;

        $bill = new Bill();
        $bill->ublVersion = '2.1';
        $bill->tipoOperacion = '0101';
        $bill->tipoDoc = strval($voucher->voucher_type);
        $bill->serie = strval($voucher->voucher_series);
        $bill->correlativo = strval($voucher->voucher_number);
        $bill->fechaEmision = new \DateTime(strval($voucher->voucher_date));
        $bill->formaPago = new PagoContado();
        $bill->tipoMoneda = strval($voucher->voucher_currency);
        $bill->client = $customer;
        $bill->mtoOperGravadas = floatval($voucher->voucher_taxed_amount);
        $bill->mtoIGV = floatval($voucher->voucher_tax_amount);
        $bill->totalImpuestos = floatval($voucher->voucher_total_taxes);
        $bill->sumOtrosCargos = floatval($voucher->voucher_other_charges ?? null);
        $bill->valorVenta = floatval($voucher->voucher_sale_value - $voucher->voucher_discount_amount);
        $bill->subTotal = floatval($voucher->voucher_sale_amount - $voucher->voucher_consumption_surcharge_amount);
        $bill->mtoImpVenta = floatval($voucher->voucher_sale_amount);
        $bill->company = $this->billUtils->getCompany();

        if (floatval($voucher->voucher_consumption_surcharge_amount) > 0) {
            $charge = new Charge();
            $charge->codTipo = '46';
            $charge->montoBase = floatval($voucher->voucher_consumption_surcharge_amount);
            $charge->factor = 1;
            $charge->monto = floatval($voucher->voucher_consumption_surcharge_amount);
            $bill->cargos = [$charge];
        }

        if (floatval($voucher->voucher_discount_amount) > 0) {
            $charge = new Charge();
            $charge->codTipo = '02';
            $charge->montoBase = floatval($voucher->voucher_discount_amount);
            $charge->factor = 1;
            $charge->monto = floatval($voucher->voucher_discount_amount);
            $bill->descuentos = [$charge];
        }

        $details = [];

        foreach ($voucher->voucher_items as $item) {
            $detail = new BillDetail();
            $detail->codProducto = strval($item->product_code);
            $detail->unidad = 'NIU';
            $detail->cantidad = intval($item->quantity);
            $detail->descripcion = strval($item->product_name);
            $detail->mtoValorUnitario = floatval($item->unit_value);
            $detail->mtoBaseIgv = floatval($item->taxed_amount);
            $detail->porcentajeIgv = floatval($item->tax_percentage);
            $detail->igv = floatval($item->tax_amount);
            $detail->tipAfeIgv = strval($item->tax_type);
            $detail->totalImpuestos = floatval($item->tax_amount);
            $detail->mtoValorVenta = floatval($item->taxed_amount);
            $detail->mtoPrecioUnitario = floatval($item->unit_price);
            $details[] = $detail;
        }

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $parts = explode('.', number_format(floatval($voucher->voucher_sale_amount), 2, '.', ''));
        $amount = $formatter->format((int) $parts[0]);
        $strTotal = mb_strtoupper('SON '.$amount.' CON '.$parts[1].' / 100 SOLES');

        $legend = new Legend();
        $legend->code = '1000';
        $legend->value = $strTotal;

        $bill->details = $details;
        $bill->legends = [$legend];
        $bill->obs = strval($voucher->voucher_note ?? null);

        return $bill;
    }
}
