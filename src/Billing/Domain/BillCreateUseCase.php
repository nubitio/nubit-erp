<?php

namespace App\Billing\Domain;

use App\Billing\Model\Address\Address;
use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Bill\BillDetail;
use App\Billing\Model\Bill\Charge;
use App\Billing\Model\Bill\PagoContado;
use App\Billing\Model\Client\Client;
use App\Billing\Model\Response\DocumentResponse;
use App\Sale\Domain\SaleRepositoryInterface;

class BillCreateUseCase
{
    private BillRepositoryInterface $billRepository;
    private SaleRepositoryInterface $saleRepository;
    private BillUtilsInterface $billUtils;

    public function __construct(
        BillRepositoryInterface $billRepository,
        SaleRepositoryInterface $saleRepository,
        BillUtilsInterface      $billUtils
    )
    {
        $this->billRepository = $billRepository;
        $this->saleRepository = $saleRepository;
        $this->billUtils = $billUtils;
    }

    public function __invoke(string $saleId): DocumentResponse
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
        $bill->mtoIgv = $sale['header']['doc_igv_amount'];
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
        foreach ($sale['details'] as $key => $value) {
            $item = new BillDetail();
            $item->codProducto = $value['code'] ?? "";
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
        $bill->details = $details;
        $response = $this->billRepository->createBill($bill);
        $response->name = $bill->getName();

        return $response;
    }
}