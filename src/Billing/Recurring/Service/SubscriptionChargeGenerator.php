<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Service;

use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionRun;
use App\Billing\Recurring\Repository\CustomerSubscriptionLineRepository;
use App\Billing\Recurring\Repository\CustomerSubscriptionRunRepository;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use App\Settings\Repository\TenantSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class SubscriptionChargeGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantSettingsRepository $tenantSettingsRepository,
        private CustomerSubscriptionRunRepository $runRepository,
        private CustomerSubscriptionLineRepository $lineRepository,
    ) {}

    public function generate(
        CustomerSubscription $subscription,
        \DateTimeImmutable $issuedDate,
        int $sellerUserId,
        string $source,
    ): CustomerSubscriptionRun {
        $plan = $subscription->getPlan();
        $periodStart = $subscription->getNextBillingDate();
        $periodEndExclusive = $periodStart->modify(sprintf('+%d months', $plan->getBillingCycleEnum()->months()));
        $periodEndInclusive = $periodEndExclusive->modify('-1 day');
        if ($subscription->getEndDate() !== null && $subscription->getEndDate() < $periodEndInclusive) {
            $periodEndInclusive = $subscription->getEndDate();
        }

        $existing = $this->runRepository->findOneBy([
            'subscription' => $subscription,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEndInclusive,
        ]);
        if ($existing instanceof CustomerSubscriptionRun) {
            throw new ConflictHttpException('Ya existe un cargo para este período.');
        }

        $configuredLines = $this->lineRepository->findForSubscription($subscription);
        $amount = Money::money('0');
        if ($configuredLines === []) {
            $amount = Money::money($subscription->getAgreedAmount() ?? $plan->getAmount());
        } else {
            foreach ($configuredLines as $configuredLine) {
                $amount = Money::add(
                    $amount,
                    Money::mul(
                        Money::of($configuredLine->getQuantity()),
                        Money::of($configuredLine->getUnitPrice()),
                    ),
                );
            }
        }
        $amountString = Money::toString($amount);

        $termDays = $plan->getPaymentTermDays()
            ?? $this->tenantSettingsRepository->getOrCreate()->getDefaultPaymentTermDays();

        $sale = (new SaleRecord())
            ->setCustomer($subscription->getCustomer())
            ->setCustomerSubscriptionId($subscription->getId())
            ->setBranch($plan->getBranch())
            ->setWarehouse($plan->getWarehouse())
            ->setSellerUserId($sellerUserId)
            ->setStatus('COMPLETED')
            ->setCurrency('PEN')
            ->setIssuedAt(\DateTime::createFromImmutable($issuedDate))
            ->setIssuedDate($issuedDate)
            ->setDueDate($issuedDate->modify(sprintf('+%d days', $termDays)))
            ->setSubtotal($amountString)
            ->setGrandTotal($amountString)
            ->setPaidTotal('0.00')
            ->setBalanceDue($amountString)
            ->setNote(sprintf('Cargo recurrente: %s', $plan->getName()));

        if ($configuredLines === []) {
            $product = $plan->getProduct();
            $description = $subscription->getCustomDescription() ?? $product->getName();
            $this->addSaleItem(
                $sale,
                $product,
                $description,
                '1.000',
                Money::toString($amount, 4),
                $amountString,
                1,
                $subscription,
                $periodStart,
                $periodEndInclusive,
            );
        } else {
            foreach ($configuredLines as $index => $configuredLine) {
                $lineAmount = Money::mul(
                    Money::of($configuredLine->getQuantity()),
                    Money::of($configuredLine->getUnitPrice()),
                );
                $this->addSaleItem(
                    $sale,
                    $configuredLine->getProduct(),
                    $configuredLine->getDescription() ?? $configuredLine->getProduct()->getName(),
                    $configuredLine->getQuantity(),
                    $configuredLine->getUnitPrice(),
                    Money::toString($lineAmount),
                    $index + 1,
                    $subscription,
                    $periodStart,
                    $periodEndInclusive,
                );
            }
        }
        $this->entityManager->persist($sale);

        $run = (new CustomerSubscriptionRun())
            ->setSubscription($subscription)
            ->setPeriodStart($periodStart)
            ->setPeriodEnd($periodEndInclusive)
            ->setSource($source)
            ->setSaleId((string) $sale->getId());
        $this->entityManager->persist($run);

        $subscription
            ->setCurrentPeriodStart($periodStart)
            ->setCurrentPeriodEnd($periodEndExclusive)
            ->setNextBillingDate($periodEndExclusive);
        if (
            !$subscription->isAutoRenew()
            || ($subscription->getEndDate() !== null && $periodEndExclusive > $subscription->getEndDate())
        ) {
            $subscription->setStatus('cancelled');
        }

        $this->entityManager->flush();

        return $run;
    }

    private function addSaleItem(
        SaleRecord $sale,
        \App\Catalog\Entity\Product $product,
        string $description,
        string $quantity,
        string $unitPrice,
        string $lineAmount,
        int $lineNumber,
        CustomerSubscription $subscription,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): void {
        if ($subscription->getServiceReference() !== null) {
            $description .= ' - ' . $subscription->getServiceReference();
        }
        $description .= sprintf(
            ' - Periodo %s al %s',
            $periodStart->format('d/m/Y'),
            $periodEnd->format('d/m/Y'),
        );

        $item = (new SaleItemRecord())
            ->setLineNo($lineNumber)
            ->setProduct($product)
            ->setQty($quantity)
            ->setUnitPrice($unitPrice)
            ->setGrossAmount($lineAmount)
            ->setNetAmount($lineAmount)
            ->setNameSnapshot(mb_substr($description, 0, 255))
            ->setSkuSnapshot($product->getSku());
        if ($product->getBaseUnit() !== null) {
            $item->setUomSnapshot($product->getBaseUnit()->getCode());
        }
        $sale->addSaleItemRecord($item);
    }
}
