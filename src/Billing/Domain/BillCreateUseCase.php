<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Repository\CompanyRepository;
use App\Repository\InvoiceRepository;
use DateMalformedStringException;
use DateTime;
use Doctrine\DBAL\Exception;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use NumberFormatter;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class BillCreateUseCase
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BillUtilsInterface $billUtils,
        private CompanyRepository $companyRepository,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function __invoke(string $saleId): Invoice
    {
        // Helper para redondear montos a 2 decimales (requerido por SUNAT)
        $round2 = static fn (?float $value): float => round(floatval($value), 2);

        $company = $this->companyRepository->findOneBy([]);
        $voucher = $this->invoiceRepository->getVoucher($saleId);
        $customerAddress = new Address()
            ->setDireccion(strval($voucher->customer_address));
        $operationType = $voucher->voucher_operation_type ?: '0101';
        $detractionPercent = $voucher->voucher_detraction_percent;
        $detractionAmount = $voucher->voucher_detraction_amount;
        $hasDetraction = $voucher->voucher_detraction_code
            || $detractionPercent !== null
            || $detractionAmount !== null
            || $voucher->voucher_detraction_payment_method
            || $voucher->voucher_detraction_value_ref !== null;

        if ($hasDetraction && !$voucher->voucher_operation_type) {
            $operationType = '1001';
        }

        if ($detractionAmount === null && $detractionPercent !== null) {
            $detractionAmount = round(
                floatval($voucher->voucher_sale_amount) * $detractionPercent / 100,
                2
            );
        }

        $customer = new Client()
            ->setTipoDoc(strval($voucher->customer_doc_type))
            ->setNumDoc(strval($voucher->customer_doc_number))
            ->setRznSocial(strval($voucher->customer_name))
            ->setAddress($customerAddress);

        $bill = new Invoice()
            ->setUblVersion('2.1')
            ->setTipoOperacion($operationType)
            ->setTipoDoc(strval($voucher->voucher_type))
            ->setSerie(strval($voucher->voucher_series))
            ->setCorrelativo(strval($voucher->voucher_number))
            ->setFechaEmision(new DateTime(strval($voucher->voucher_date)))
            ->setFormaPago(
                2 == $voucher->voucher_payment_method ? new FormaPagoCredito(
                    $round2($voucher->voucher_sale_amount)
                ) : new FormaPagoContado()
            )
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setClient($customer)
            ->setMtoOperGravadas($round2($voucher->voucher_taxed_amount))
            ->setMtoIGV($round2($voucher->voucher_tax_amount))
            ->setTotalImpuestos($round2($voucher->voucher_total_taxes))
            ->setSumOtrosCargos($round2($voucher->voucher_consumption_surcharge_amount ?? 0))
            ->setValorVenta($round2($voucher->voucher_sale_value - $voucher->voucher_discount_amount))
            ->setSubTotal($round2($voucher->voucher_sale_amount - $voucher->voucher_consumption_surcharge_amount))
            ->setMtoImpVenta($round2($voucher->voucher_sale_amount))
            ->setCompany($this->billUtils->getCompany());

        if ($hasDetraction) {
            $detractionAccount = $company->getDetraccionAccount() ?: $voucher->voucher_detraction_account;
            $detraction = (new Detraction())
                ->setPercent($detractionPercent)
                ->setMount($detractionAmount)
                ->setCtaBanco($detractionAccount)
                ->setCodMedioPago($voucher->voucher_detraction_payment_method)
                ->setCodBienDetraccion($voucher->voucher_detraction_code)
                ->setValueRef($voucher->voucher_detraction_value_ref);
            $bill->setDetraccion($detraction);
        }

        if (2 == $voucher->voucher_payment_method) {
            $paymentDate = new DateTime(strval($voucher->voucher_date))->modify('+1 month');

            $bill->setCuotas([
                new Cuota()
                    ->setFechaPago($paymentDate)
                    ->setMonto($round2($voucher->voucher_sale_amount)),
            ]);
        }

        if (floatval($voucher->voucher_consumption_surcharge_amount) > 0) {
            $surchargeAmount = $round2($voucher->voucher_consumption_surcharge_amount);
            $charge = new Charge()
                ->setCodTipo('46')
                ->setMontoBase($surchargeAmount)
                ->setFactor(1)
                ->setMonto($surchargeAmount);
            $bill->setCargos([$charge]);
        }

        if (floatval($voucher->voucher_discount_amount) > 0) {
            $discountAmount = $round2($voucher->voucher_discount_amount);
            $charge = new Charge()
                ->setCodTipo('02')
                ->setMontoBase($discountAmount)
                ->setFactor(1)
                ->setMonto($discountAmount);
            $bill->setDescuentos([$charge]);
        }

        $details = [];

        foreach ($voucher->voucher_items as $item) {
            $detail = new SaleDetail()
                ->setCodProducto(strval($item->product_code))
                ->setUnidad($item->product_uom ?? 'NIU')
                ->setCantidad(floatval($item->quantity))
                ->setDescripcion(strval($item->product_name))
                ->setMtoValorUnitario($round2($item->unit_value))
                ->setMtoBaseIgv($round2($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv($round2($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos($round2($item->tax_amount))
                ->setMtoValorVenta($round2($item->taxed_amount))
                ->setMtoPrecioUnitario($round2($item->unit_price));
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
        $legends = [$legend];
        if ($hasDetraction) {
            $legends[] = (new Legend())
                ->setCode('2006')
                ->setValue('Operación sujeta a detracción');
        }
        $bill->setLegends($legends);
        $bill->setObservacion(strval($voucher->voucher_note ?? null));

        return $bill;
    }
}
