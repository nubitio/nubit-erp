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

class BillCreateUseCase
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly BillUtilsInterface $billUtils,
    ) {
    }

    public function __invoke(string $saleId): Bill
    {
        $sale = $this->saleRepository->getOneById($saleId);
        $customerAddress = new Address();
        $customerAddress->direccion = $sale['header']['client_address'];

        $customer = new Client();
        $customer->tipoDoc = $sale['header']['client_doc_type'];
        $customer->numDoc = $sale['header']['client_doc_number'];
        $customer->rznSocial = $sale['header']['client_name'];
        $customer->address = $customerAddress;

        $bill = new Bill();
        $bill->ublVersion = '2.1';
        $bill->tipoOperacion = '0101';
        $bill->tipoDoc = $sale['header']['doc_type'];
        $bill->serie = $sale['header']['doc_series'];
        $bill->correlativo = $sale['header']['doc_number'];
        $bill->fechaEmision = new \DateTime($sale['header']['doc_date']);
        $bill->formaPago = new PagoContado();
        $bill->tipoMoneda = $sale['header']['doc_currency'];
        $bill->client = $customer;
        $bill->mtoOperGravadas = $sale['header']['doc_taxed_amount'];
        $bill->mtoIGV = $sale['header']['doc_igv_amount'];
        $bill->totalImpuestos = $sale['header']['doc_total_taxes'];
        $bill->sumOtrosCargos = $sale['header']['doc_other_charges'] ?? null;
        $bill->valorVenta = $sale['header']['doc_sale_value'] - $sale['header']['doc_discount_amount'];
        $bill->subTotal = $sale['header']['doc_sale_amount'] - $sale['header']['doc_other_charges'];
        $bill->mtoImpVenta = $sale['header']['doc_sale_amount'];
        $bill->company = $this->billUtils->getCompany();

        if ($sale['header']['doc_other_charges'] > 0) {
            $charge = new Charge();
            $charge->codTipo = '46';
            $charge->montoBase = $sale['header']['doc_taxed_amount'];
            $charge->factor = $sale['header']['doc_other_charges_percent'];
            $charge->monto = $sale['header']['doc_other_charges'];
            $bill->cargos = [$charge];
        }

        if ($sale['header']['doc_discount_amount'] > 0) {
            $charge = new Charge();
            $charge->codTipo = '02';
            $charge->montoBase = $sale['header']['doc_discount_amount'];
            $charge->factor = 1;
            $charge->monto = $sale['header']['doc_discount_amount'];
            $bill->descuentos = [$charge];
        }

        $details = [];
        foreach ($sale['details'] as $value) {
            $item = new BillDetail();
            $item->codProducto = $value['code'] ?? '';
            $item->unidad = 'NIU';
            $item->cantidad = $value['quantity'];
            $item->descripcion = $value['product'];
            $item->mtoValorUnitario = $value['unit_value'];
            $item->mtoBaseIgv = $value['taxed_amount'];
            $item->porcentajeIgv = $value['tax_percent'];
            $item->igv = $value['tax_amount'];
            $item->tipAfeIgv = $value['tax_type'];
            $item->totalImpuestos = $value['tax_amount'];
            $item->mtoValorVenta = $value['taxed_amount'];
            $item->mtoPrecioUnitario = $value['unit_price'];
            $details[] = $item;
        }

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $parts = explode('.', $sale['header']['doc_sale_amount']);
        $amount = $formatter->format($parts[0]);
        $strTotal = mb_strtoupper('SON '.$amount.' CON '.$parts[1].'/100 SOLES');

        $legend = new Legend();
        $legend->code = '1000';
        $legend->value = $strTotal;

        $bill->details = $details;
        $bill->legends = [$legend];
        $bill->obs = $sale['header']['doc_notes'] ?? null;

        return $bill;
    }
}
