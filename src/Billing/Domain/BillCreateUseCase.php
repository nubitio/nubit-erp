<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Core\Util\Money;
use App\Repository\CompanyRepository;
use App\Repository\TaxDocumentRepository;
use Brick\Math\Exception\MathException;
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

readonly class BillCreateUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private BillUtilsInterface $billUtils,
        private CompanyRepository $companyRepository,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws MathException
     */
    public function __invoke(string $saleId): Invoice
    {
        $money = static fn (mixed $value): float => Money::toFloat(Money::money($value));
        $toNullableFloat = static fn (mixed $value): ?float => $value === null || $value === '' ? null : floatval(
            $value
        );

        $company = $this->companyRepository->findOneBy([]);
        $voucher = $this->taxDocumentRepository->getVoucher($saleId);
        $customerAddress = new Address()
            ->setDireccion(strval($voucher->customer_address));
        $operationType = $voucher->voucher_operation_type ?: '0101';
        $detractionPercent = $toNullableFloat($voucher->voucher_detraction_percent);
        $detractionAmount = $toNullableFloat($voucher->voucher_detraction_amount);
        $detractionValueRef = $toNullableFloat($voucher->voucher_detraction_value_ref);
        $hasDetraction = $voucher->voucher_detraction_code
            || $detractionPercent !== null
            || $detractionAmount !== null
            || $voucher->voucher_detraction_payment_method
            || $detractionValueRef !== null;

        if ($hasDetraction && !$voucher->voucher_operation_type) {
            $operationType = '1001';
        }

        if ($detractionAmount === null && $detractionPercent !== null) {
            $detractionAmount = $money(
                Money::of($voucher->voucher_sale_amount)->multipliedBy(Money::rate($detractionPercent))
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
                    $money($voucher->voucher_sale_amount)
                ) : new FormaPagoContado()
            )
            ->setTipoMoneda(strval($voucher->voucher_currency))
            ->setClient($customer)
            ->setMtoOperGravadas($money($voucher->voucher_taxed_amount))
            ->setMtoIGV($money($voucher->voucher_tax_amount))
            ->setTotalImpuestos($money($voucher->voucher_total_taxes))
            ->setSumOtrosCargos($money($voucher->voucher_consumption_surcharge_amount ?? 0))
            ->setValorVenta(
                $money(Money::of($voucher->voucher_sale_value)->minus(Money::of($voucher->voucher_discount_amount)))
            )
            ->setSubTotal(
                $money(
                    Money::of($voucher->voucher_sale_amount)->minus(
                        Money::of($voucher->voucher_consumption_surcharge_amount)
                    )
                )
            )
            ->setMtoImpVenta($money($voucher->voucher_sale_amount))
            ->setCompany($this->billUtils->getCompany());

        if ($hasDetraction) {
            $detractionAccount = $company->getDetraccionAccount() ?: $voucher->voucher_detraction_account;
            $detraction = new Detraction()
                ->setPercent($detractionPercent)
                ->setMount($detractionAmount)
                ->setCtaBanco($detractionAccount)
                ->setCodMedioPago($voucher->voucher_detraction_payment_method)
                ->setCodBienDetraccion($voucher->voucher_detraction_code)
                ->setValueRef($detractionValueRef);
            $bill->setDetraccion($detraction);
        }

        if (2 == $voucher->voucher_payment_method) {
            $paymentDate = new DateTime(strval($voucher->voucher_date))->modify('+1 month');

            $bill->setCuotas([
                new Cuota()
                    ->setFechaPago($paymentDate)
                    ->setMonto($money($voucher->voucher_sale_amount)),
            ]);
        }

        if (floatval($voucher->voucher_consumption_surcharge_amount) > 0) {
            $surchargeAmount = $money($voucher->voucher_consumption_surcharge_amount);
            $charge = new Charge()
                ->setCodTipo('46')
                ->setMontoBase($surchargeAmount)
                ->setFactor(1)
                ->setMonto($surchargeAmount);
            $bill->setCargos([$charge]);
        }

        if (floatval($voucher->voucher_discount_amount) > 0) {
            $discountAmount = $money($voucher->voucher_discount_amount);
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
                ->setMtoValorUnitario($money($item->unit_value))
                ->setMtoBaseIgv($money($item->taxed_amount))
                ->setPorcentajeIgv(floatval($item->tax_percentage))
                ->setIgv($money($item->tax_amount))
                ->setTipAfeIgv(strval($item->tax_type))
                ->setTotalImpuestos($money($item->tax_amount))
                ->setMtoValorVenta($money($item->taxed_amount))
                ->setMtoPrecioUnitario($money($item->unit_price));
            $details[] = $detail;
        }

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $amountString = Money::toString(Money::money($voucher->voucher_sale_amount));
        $parts = explode('.', $amountString);
        $amount = $formatter->format((int)$parts[0]);
        $strTotal = mb_strtoupper('SON ' . $amount . ' CON ' . $parts[1] . ' / 100 SOLES');

        $legend = new Legend()
            ->setCode('1000')
            ->setValue($strTotal);

        $bill->setDetails($details);
        $legends = [$legend];
        if ($hasDetraction) {
            $legends[] = new Legend()
                ->setCode('2006')
                ->setValue('Operación sujeta a detracción');
        }
        $bill->setLegends($legends);
        $bill->setObservacion(strval($voucher->voucher_note ?? null));

        return $bill;
    }
}
