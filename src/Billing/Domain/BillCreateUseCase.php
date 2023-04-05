<?php

namespace App\Billing\Domain;

use App\Billing\Model\Address\Address;
use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Bill\BillDetail;
use App\Billing\Model\Bill\Charge;
use App\Billing\Model\Bill\Legend;
use App\Billing\Model\Bill\PagoContado;
use App\Billing\Model\Client\Client;
use App\Sale\Domain\SaleRepositoryInterface;

readonly class BillCreateUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private BillUtilsInterface      $billUtils,
    )
    {
    }

    public function __invoke(string $saleId): Bill
    {
        $sale = $this->saleRepository->getOneById($saleId);
        $customerAddress = new Address();
        $customerAddress->direccion = strval($sale['header']['client_address']);

        $customer = new Client();
        $customer->tipoDoc = strval($sale['header']['client_doc_type']);
        $customer->numDoc = strval($sale['header']['client_doc_number']);
        $customer->rznSocial = strval($sale['header']['client_name']);
        $customer->address = $customerAddress;

        $bill = new Bill();
        $bill->ublVersion = '2.1';
        $bill->tipoOperacion = '0101';
        $bill->tipoDoc = strval($sale['header']['doc_type']);
        $bill->serie = strval($sale['header']['doc_series']);
        $bill->correlativo = strval($sale['header']['doc_number']);
        $bill->fechaEmision = new \DateTime(strval($sale['header']['doc_date']));
        $bill->formaPago = new PagoContado();
        $bill->tipoMoneda = strval($sale['header']['doc_currency']);
        $bill->client = $customer;
        $bill->mtoOperGravadas = floatval($sale['header']['doc_taxed_amount']);
        $bill->mtoIGV = floatval($sale['header']['doc_igv_amount']);
        $bill->totalImpuestos = floatval($sale['header']['doc_total_taxes']);
        $bill->sumOtrosCargos = floatval($sale['header']['doc_other_charges'] ?? null);
        $bill->valorVenta = floatval($sale['header']['doc_sale_value'] - $sale['header']['doc_discount_amount']);
        $bill->subTotal = floatval($sale['header']['doc_sale_amount'] - $sale['header']['doc_other_charges']);
        $bill->mtoImpVenta = floatval($sale['header']['doc_sale_amount']);
        $bill->company = $this->billUtils->getCompany();

        if (floatval($sale['header']['doc_other_charges']) > 0) {
            $charge = new Charge();
            $charge->codTipo = '46';
            $charge->montoBase = floatval($sale['header']['doc_taxed_amount']);
            $charge->factor = floatval($sale['header']['doc_other_charges_percent']);
            $charge->monto = floatval($sale['header']['doc_other_charges']);
            $bill->cargos = [$charge];
        }

        if (floatval($sale['header']['doc_discount_amount']) > 0) {
            $charge = new Charge();
            $charge->codTipo = '02';
            $charge->montoBase = floatval($sale['header']['doc_discount_amount']);
            $charge->factor = 1;
            $charge->monto = floatval($sale['header']['doc_discount_amount']);
            $bill->descuentos = [$charge];
        }

        $details = [];
        /** @var array<string, mixed> $value */
        foreach ($sale['details'] as $value) {
            $item = new BillDetail();
            $item->codProducto = strval($value['code'] ?? '');
            $item->unidad = 'NIU';
            $item->cantidad = intval($value['quantity']);
            $item->descripcion = strval($value['product']);
            $item->mtoValorUnitario = floatval($value['unit_value']);
            $item->mtoBaseIgv = floatval($value['taxed_amount']);
            $item->porcentajeIgv = floatval($value['tax_percent']);
            $item->igv = floatval($value['tax_amount']);
            $item->tipAfeIgv = strval($value['tax_type']);
            $item->totalImpuestos = floatval($value['tax_amount']);
            $item->mtoValorVenta = floatval($value['taxed_amount']);
            $item->mtoPrecioUnitario = floatval($value['unit_price']);
            $details[] = $item;
        }

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $parts = explode('.', strval($sale['header']['doc_sale_amount']));
        $amount = $formatter->format((int)$parts[0]);
        $strTotal = mb_strtoupper('SON ' . $amount . ' CON ' . $parts[1] . '/100 SOLES');

        $legend = new Legend();
        $legend->code = '1000';
        $legend->value = $strTotal;

        $bill->details = $details;
        $bill->legends = [$legend];
        $bill->obs = strval($sale['header']['doc_notes'] ?? null);

        return $bill;
    }
}
