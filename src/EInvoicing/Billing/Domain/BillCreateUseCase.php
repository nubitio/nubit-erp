<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\Core\Exception\ServiceException;
use App\Core\Util\Money;
use App\EInvoicing\Invoice\Service\VoucherHydratorInterface;
use App\EInvoicing\Shared\Enum\ChargeType;
use App\EInvoicing\Shared\Enum\LegendCode;
use App\EInvoicing\Shared\Enum\OperationType;
use App\EInvoicing\Shared\Service\AmountFormatterService;
use App\Repository\CompanyRepository;
use App\Repository\TaxDocumentRepository;
use Brick\Math\Exception\MathException;
use DateMalformedStringException;
use Greenter\Model\Client\Client;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Detraction;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;

readonly class BillCreateUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private BillUtilsInterface $billUtils,
        private CompanyRepository $companyRepository,
        private VoucherHydratorInterface $voucherHydrator,
        private AmountFormatterService $amountFormatterService,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     * @throws MathException
     */
    public function __invoke(string $taxDocumentId): Invoice
    {
        $taxDocument = $this->taxDocumentRepository->find($taxDocumentId);

        if ($taxDocument === null) {
            throw new ServiceException('Invoice not found');
        }

        $voucher = $this->taxDocumentRepository->getVoucher($taxDocument->getSale()->getId());
        $hydratedVoucher = $this->voucherHydrator->hydrate($voucher);

        $company = $this->companyRepository->findOneBy([]);
        $customerAddress = $hydratedVoucher->customerAddress;
        $operationType = $hydratedVoucher->voucherOperationType ?: OperationType::SALE->value;
        $detractionPercent = $hydratedVoucher->voucherDetractionPercent;
        $detractionAmount = $hydratedVoucher->voucherDetractionAmount;
        $detractionValueRef = $hydratedVoucher->voucherDetractionValueRef;
        $hasDetraction = $hydratedVoucher->voucherDetractionCode
            || $detractionPercent !== null
            || $detractionAmount !== null
            || $hydratedVoucher->voucherDetractionPaymentMethod
            || $detractionValueRef !== null;

        if ($hasDetraction && !$hydratedVoucher->voucherOperationType) {
            $operationType = OperationType::DETRACTION_SALE->value;
        }

        if ($detractionAmount === null && $detractionPercent !== null) {
            $detractionAmount = Money::toFloat(
                Money::of($hydratedVoucher->voucherSaleAmount)->multipliedBy(Money::rate($detractionPercent))
            );
        }

        $customer = new Client()
            ->setTipoDoc($hydratedVoucher->customerDocType)
            ->setNumDoc($hydratedVoucher->customerDocNumber)
            ->setRznSocial($hydratedVoucher->customerName)
            ->setAddress($customerAddress);

        $bill = new Invoice()
            ->setUblVersion('2.1')
            ->setTipoOperacion($operationType)
            ->setTipoDoc($hydratedVoucher->voucherType)
            ->setSerie($hydratedVoucher->voucherSeries)
            ->setCorrelativo($hydratedVoucher->voucherNumber)
            ->setFechaEmision($hydratedVoucher->voucherDate)
            ->setFormaPago(
                2 === $hydratedVoucher->voucherPaymentMethod ? new FormaPagoCredito(
                    $hydratedVoucher->voucherSaleAmount
                ) : new FormaPagoContado()
            )
            ->setTipoMoneda($hydratedVoucher->voucherCurrency)
            ->setClient($customer)
            ->setMtoOperGravadas($hydratedVoucher->voucherTaxedAmount)
            ->setMtoIGV($hydratedVoucher->voucherTaxAmount)
            ->setTotalImpuestos($hydratedVoucher->voucherTotalTaxes)
            ->setSumOtrosCargos($hydratedVoucher->voucherConsumptionSurchargeAmount)
            ->setValorVenta(
                Money::of($hydratedVoucher->voucherSaleValue)->minus(
                    Money::of($hydratedVoucher->voucherDiscountAmount)
                )->toFloat()
            )
            ->setSubTotal(
                Money::of($hydratedVoucher->voucherSaleAmount)->minus(
                    Money::of($hydratedVoucher->voucherConsumptionSurchargeAmount)
                )->toFloat()
            )
            ->setMtoImpVenta($hydratedVoucher->voucherSaleAmount)
            ->setCompany($this->billUtils->getCompany());

        if ($hasDetraction) {
            $detractionAccount = $company->getDetraccionAccount() ?: $hydratedVoucher->voucherDetractionAccount;
            $detraction = new Detraction()
                ->setPercent($detractionPercent)
                ->setMount($detractionAmount)
                ->setCtaBanco($detractionAccount)
                ->setCodMedioPago($hydratedVoucher->voucherDetractionPaymentMethod)
                ->setCodBienDetraccion($hydratedVoucher->voucherDetractionCode)
                ->setValueRef($detractionValueRef);
            $bill->setDetraccion($detraction);
        }

        if (2 === $hydratedVoucher->voucherPaymentMethod) {
            $paymentDate = $hydratedVoucher->voucherDate->modify('+1 month');

            $bill->setCuotas([
                new Cuota()
                    ->setFechaPago($paymentDate)
                    ->setMonto($hydratedVoucher->voucherSaleAmount),
            ]);
        }

        if ($hydratedVoucher->voucherConsumptionSurchargeAmount > 0) {
            $surchargeAmount = $hydratedVoucher->voucherConsumptionSurchargeAmount;
            $charge = new Charge()
                ->setCodTipo(ChargeType::CONSUMPTION_SURCHARGE->value)
                ->setMontoBase($surchargeAmount)
                ->setFactor(1)
                ->setMonto($surchargeAmount);
            $bill->setCargos([$charge]);
        }

        if ($hydratedVoucher->voucherDiscountAmount > 0) {
            $discountAmount = $hydratedVoucher->voucherDiscountAmount;
            $charge = new Charge()
                ->setCodTipo(ChargeType::DISCOUNT->value)
                ->setMontoBase($discountAmount)
                ->setFactor(1)
                ->setMonto($discountAmount);
            $bill->setDescuentos([$charge]);
        }

        $details = [];

        foreach ($hydratedVoucher->voucherItems as $item) {
            $detail = new SaleDetail()
                ->setCodProducto($item->productCode)
                ->setUnidad($item->productUom)
                ->setCantidad($item->quantity)
                ->setDescripcion($item->productName)
                ->setMtoValorUnitario($item->unitValue)
                ->setMtoBaseIgv($item->taxedAmount)
                ->setPorcentajeIgv($item->taxPercentage)
                ->setIgv($item->taxAmount)
                ->setTipAfeIgv($item->taxType)
                ->setTotalImpuestos($item->taxAmount)
                ->setMtoValorVenta($item->taxedAmount)
                ->setMtoPrecioUnitario($item->unitPrice);
            $details[] = $detail;
        }

        $strTotal = $this->amountFormatterService->formatAmountInWords(
            $hydratedVoucher->voucherSaleAmount,
            $hydratedVoucher->voucherCurrency
        );

        $legend = new Legend()
            ->setCode(LegendCode::TOTAL_AMOUNT_IN_WORDS->value)
            ->setValue($strTotal);

        $bill->setDetails($details);
        $legends = [$legend];
        if ($hasDetraction) {
            $legends[] = new Legend()
                ->setCode(LegendCode::DETRACTION_OPERATION->value)
                ->setValue('Operación sujeta a detracción');
        }
        $bill->setLegends($legends);
        $bill->setObservacion($hydratedVoucher->voucherNote ?? null);

        return $bill;
    }
}
