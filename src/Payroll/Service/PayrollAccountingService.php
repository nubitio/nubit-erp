<?php

declare(strict_types=1);

namespace App\Payroll\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\Money;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Entity\PayslipEntry;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Turns an approved/paid PayrollRun into economic events for the general
 * ledger. EconomicEvent is routed synchronously, so a posting failure aborts
 * the caller before its own flush (fail closed).
 */
readonly class PayrollAccountingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?MessageBusInterface $messageBus = null,
    ) {}

    public function postAccrual(PayrollRun $run): void
    {
        $this->messageBus?->dispatch($this->accrualEvent($run));
    }

    /** Accrual event for an approved run, dated on its approval. */
    public function accrualEvent(PayrollRun $run): EconomicEvent
    {
        $totals = $this->totals($run);
        $expense = Money::add(Money::add($totals['gross'], $totals['bonus']), $totals['essalud']);

        return new EconomicEvent(
            source: EconomicEvent::SOURCE_PAYROLL_ACCRUAL,
            sourceId: (string) $run->getId(),
            kind: EconomicEvent::PAYROLL_ACCRUAL,
            occurredOn: ($run->getApprovedAt() ?? new DateTimeImmutable())->format('Y-m-d'),
            description: sprintf('Planilla %s %s', $run->getType(), $run->getPeriod()->format('Y-m')),
            total: Money::toString($expense),
            components: [
                DefaultPostingService::PAYROLL_GROSS => Money::toString($totals['gross']),
                DefaultPostingService::PAYROLL_BONUS => Money::toString($totals['bonus']),
                DefaultPostingService::PAYROLL_ESSALUD_EMPLOYER => Money::toString($totals['essalud']),
                DefaultPostingService::PAYROLL_AFP => Money::toString($totals['afp']),
                DefaultPostingService::PAYROLL_ONP => Money::toString($totals['onp']),
                DefaultPostingService::PAYROLL_INCOME_TAX => Money::toString($totals['income_tax']),
                DefaultPostingService::PAYROLL_NET => Money::toString($totals['net']),
            ],
        );
    }

    public function postPayment(PayrollRun $run, ?int $paymentMethodId = null): void
    {
        $this->messageBus?->dispatch($this->paymentEvent($run, $paymentMethodId));
    }

    /**
     * Settlement event for a paid run, dated on its payment.
     *
     * @param int|null $paymentMethodId PaymentMethod::CASH pays from caja; anything else (or null) from banco
     */
    public function paymentEvent(PayrollRun $run, ?int $paymentMethodId = null): EconomicEvent
    {
        $totals = $this->totals($run);

        return new EconomicEvent(
            source: EconomicEvent::SOURCE_PAYROLL_PAYMENT,
            sourceId: (string) $run->getId(),
            kind: $paymentMethodId === PaymentMethod::CASH
                ? EconomicEvent::PAYROLL_PAYMENT_CASH
                : EconomicEvent::PAYROLL_PAYMENT_BANK,
            occurredOn: ($run->getPaidAt() ?? new DateTimeImmutable())->format('Y-m-d'),
            description: sprintf('Pago de planilla %s %s', $run->getType(), $run->getPeriod()->format('Y-m')),
            total: Money::toString($totals['net']),
        );
    }

    /** What the run pays out: the sum of its employees' net pay, as a decimal string. */
    public function netPayable(PayrollRun $run): string
    {
        return Money::toString($this->totals($run)['net']);
    }

    /**
     * @return array{gross: BigDecimal, bonus: BigDecimal, essalud: BigDecimal, afp: BigDecimal, onp: BigDecimal, income_tax: BigDecimal, net: BigDecimal}
     */
    private function totals(PayrollRun $run): array
    {
        $totals = [
            'gross' => BigDecimal::zero(),
            'bonus' => BigDecimal::zero(),
            'essalud' => BigDecimal::zero(),
            'afp' => BigDecimal::zero(),
            'onp' => BigDecimal::zero(),
            'income_tax' => BigDecimal::zero(),
            'net' => BigDecimal::zero(),
        ];

        /** @var list<PayslipEntry> $entries */
        $entries = $this->entityManager->getRepository(PayslipEntry::class)->findBy(['payrollRun' => $run]);
        foreach ($entries as $entry) {
            $totals['gross'] = Money::add($totals['gross'], Money::money($entry->getGrossSalary()));
            $totals['bonus'] = Money::add($totals['bonus'], Money::money($entry->getBonificacionExtraordinaria()));
            $totals['essalud'] = Money::add($totals['essalud'], Money::money($entry->getEssaludEmployerContribution()));
            $totals['afp'] = Money::add($totals['afp'], Money::add(
                Money::add(
                    Money::money($entry->getAfpMandatoryContribution()),
                    Money::money($entry->getAfpInsurancePremium()),
                ),
                Money::money($entry->getAfpCommission()),
            ));
            $totals['onp'] = Money::add($totals['onp'], Money::money($entry->getOnpAmount()));
            $totals['income_tax'] = Money::add(
                $totals['income_tax'],
                Money::money($entry->getQuintaCategoriaRetention()),
            );
            $totals['net'] = Money::add($totals['net'], Money::money($entry->getNetPay()));
        }

        return $totals;
    }
}
