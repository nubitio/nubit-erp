<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\Billing\Service\InvoiceAmounts;
use App\Billing\Service\InvoiceCalculator;
use App\Billing\Service\InvoiceLineInput;
use App\CashRegister\Entity\CashSession;
use App\Catalog\Entity\PaymentMethod;
use App\Sales\Entity\SaleDiscount;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Settings\Service\TenantSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/** Builds the sale itself (lines, amounts, discount, credit terms, and what later happened to it) from a plan. */
final readonly class SaleRecordFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantSettingsService $tenantSettings,
    ) {}

    /** @return array{0: SaleRecord, 1: InvoiceAmounts} */
    public function build(
        SalePlan $plan,
        CashSession $session,
        SeedContext $context,
        string $series,
        string $number,
    ): array {
        $user = $context->cashiers[$plan->cashier];
        $userId = (int) $user->getId();
        $method = $context->paymentMethods[$plan->payment] ?? throw new RuntimeException('Falta el medio de pago '
        . $plan->payment);
        $issuedLima = $plan->at;
        $issuedAt = Clock::utc($issuedLima);
        $igv = (string) $this->tenantSettings->getIgv();

        $sale = new SaleRecord()
            ->setAlmacen($context->warehouse)
            ->setWarehouse($context->warehouse)
            ->setBranch($context->branch)
            ->setCliente($plan->customerKey !== null ? $context->customers[$plan->customerKey] : null)
            ->setFecha($issuedAt)
            ->setIssuedDate($issuedLima->setTime(0, 0))
            ->setFormaPago($plan->isCredit() ? 2 : PaymentMethod::CASH)
            ->setTipoPago($method)
            ->setSerie($series)
            ->setNumero($number)
            ->setDescuento(Clock::money($plan->discountPercent))
            ->setHasInvoice(true)
            ->setInvoice($series . '-' . $number)
            ->setCajaId($context->cashRegister->getId())
            ->setCashSessionId($session->getId())
            ->setEstado(1)
            ->setStatus('COMPLETED')
            ->setDateCreated(Clock::utcImmutable($issuedLima))
            ->setCreatedBy($userId)
            ->setSellerUserId($userId)
            ->setCreatedAt($issuedAt)
            ->setLastModifiedAt($issuedAt)
            ->setSyncedAt($issuedAt)
            ->setIgv($igv)
            ->setRecargoConsumo(0)
            ->setTipoOperacion('0101')
            ->setCurrency('PEN');

        $inputs = $this->addLines($sale, $plan, $context, $issuedAt);
        $amounts = new InvoiceCalculator($inputs, (string) $plan->discountPercent, $igv, '0')->calculate();
        $this->applyAmounts($sale, $amounts, $plan);
        $this->applyOutcome($sale, $plan, $userId);
        $this->entityManager->persist($sale);

        return [$sale, $amounts];
    }

    /** @return list<InvoiceLineInput> */
    private function addLines(SaleRecord $sale, SalePlan $plan, SeedContext $context, \DateTime $issuedAt): array
    {
        $inputs = [];
        foreach ($plan->lines as $index => $line) {
            $product = $context->products[$line['code']];
            $inputs[] = new InvoiceLineInput(
                quantity: (string) $line['qty'],
                unitPrice: (string) $product->getSalePrice(),
            );
            $item = new SaleItemRecord()
                ->setProduct($product)
                ->setQty(number_format((float) $line['qty'], 3, '.', ''))
                ->setUnitPrice(number_format((float) $product->getSalePrice(), 4, '.', ''))
                ->setDiscountAmount('0.00')
                ->setLineNo($index + 1)
                ->setNameSnapshot($product->getName())
                ->setSkuSnapshot($product->getCode())
                ->setUomSnapshot($product->getUnitOfMeasure()->getCode())
                ->setUnitCostSnapshot($product->getPurchasePrice())
                ->setLastModifiedAt($issuedAt)
                ->setSyncedAt($issuedAt);
            $this->entityManager->persist($item);
            $sale->addSaleItemRecord($item);
        }

        return $inputs;
    }

    private function applyAmounts(SaleRecord $sale, InvoiceAmounts $amounts, SalePlan $plan): void
    {
        $credit = $plan->isCredit();
        $sale
            ->setSubtotal($amounts->getSubtotal())
            ->setTaxTotal($amounts->getTax())
            ->setDiscountTotal($amounts->getDiscountAmount())
            ->setTotal($amounts->getTotal())
            ->setPaidTotal($credit ? '0.00' : $amounts->getTotal())
            ->setBalanceDue($credit ? $amounts->getTotal() : '0.00');

        $lineAmounts = $amounts->getLines();
        foreach ($sale->getSaleItemRecords() as $index => $item) {
            $line = $lineAmounts[$index] ?? null;
            if ($line !== null) {
                $item
                    ->setGrossAmount($line->totalAmount)
                    ->setTaxAmount($line->taxAmount)
                    ->setNetAmount($line->taxableAmount);
            }
        }

        if ($credit) {
            $sale->setDueDate(
                $plan->at->setTime(0, 0)->modify(sprintf('+%d days', $plan->creditDays)),
            )->setReceivableAccount(true);
        }
        if ($plan->discountPercent > 0.0) {
            $sale->addSaleDiscount(
                new SaleDiscount()
                    ->setDiscountType('PERCENT')
                    ->setSource('MANUAL')
                    ->setValue(number_format($plan->discountPercent, 4, '.', ''))
                    ->setAmountApplied($amounts->getDiscountAmount())
                    ->setReasonCode('MANUAL')
                    ->setReasonText($plan->discountReason)
                    ->setCreatedAt(Clock::utc($plan->at)),
            );
        }
    }

    private function applyOutcome(SaleRecord $sale, SalePlan $plan, int $userId): void
    {
        if ($plan->outcome === SalePlan::NORMAL || $plan->outcomeAt === null) {
            return;
        }

        $reason = $plan->outcome === SalePlan::CREDIT_NOTE
            ? 'Nota de crédito (' . $plan->outcomeReason . ')'
            : $plan->outcomeReason;
        $sale->setStatus('VOIDED')->recordVoid($userId, Clock::utc($plan->outcomeAt), $reason)->setGlosa($reason);
    }
}
