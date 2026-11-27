<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;
use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What a credit note does to a credit sale's debt (finance refactoring G-04, decision D-01).
 *
 * A credit note is not a movement of money. It lowers what the customer owes, up to the open balance;
 * whatever the note covers beyond that was already collected, so it becomes a customer advance
 * (saldo a favor) that can be applied to another sale or refunded later. No cash or bank movement is
 * written here: returning money is a separate, explicit action.
 *
 * Only credit sales (`receivable_account`) with a known customer are handled; the cash-sale refund path
 * is unchanged. Idempotent per credit note. Not yet posted to the general ledger: the accounting side of
 * credit notes does not exist yet and is tracked as a follow-up.
 */
readonly class CreditNoteReceivableAdjuster
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerAccountLedger $ledger,
        private CustomerAccountEntryRepository $entries,
    ) {}

    /**
     * @return array{reduced: string, creditedToCustomer: string}
     */
    public function apply(
        SaleRecord $sale,
        string $creditNoteTotal,
        string $creditNoteId,
        string $label,
        DateTimeImmutable $occurredOn,
    ): array {
        $none = ['reduced' => '0.00', 'creditedToCustomer' => '0.00'];

        $customerId = $sale->getCustomer()?->getId();
        if ($customerId === null || !$sale->isReceivableAccount()) {
            return $none;
        }
        if (
            $this->entries->hasEntryFromSource('credit_note', $creditNoteId)
            || $this->entries->hasEntryFromSource('credit_note', $creditNoteId . ':excess')
        ) {
            return $none;
        }

        // Lock and re-read: the open balance below must be the one concurrent collections left.
        $this->entityManager->refresh($sale, LockMode::PESSIMISTIC_WRITE);

        $total = Money::money($creditNoteTotal);
        if (!$total->isPositive()) {
            return $none;
        }

        $currency = $sale->getCurrency() !== '' ? $sale->getCurrency() : 'PEN';
        $open = Money::money($sale->getBalanceDue());
        $reduction = BigDecimal::min($total, $open);
        $excess = $total->minus($reduction);
        $description = sprintf('Nota de crédito %s', $label);

        if ($reduction->isPositive()) {
            $sale->setBalanceDue(Money::toString($open->minus($reduction)));
            $this->ledger->record(
                customerId: (int) $customerId,
                entryType: CustomerAccountEntryType::CREDIT_NOTE,
                occurredOn: $occurredOn,
                debit: '0.00',
                credit: Money::toString($reduction),
                currency: $currency,
                saleId: (string) $sale->getId(),
                source: 'credit_note',
                sourceId: $creditNoteId,
                description: $description,
            );
        }

        if ($excess->isPositive()) {
            $this->ledger->recordAdvanceReceived(
                customerId: (int) $customerId,
                occurredOn: $occurredOn,
                amount: Money::toString($excess),
                currency: $currency,
                source: 'credit_note',
                sourceId: $creditNoteId . ':excess',
                description: $description . ' (saldo a favor)',
                branchId: $sale->getBranch()?->getId(),
            );
        }

        $this->entityManager->flush();

        return ['reduced' => Money::toString($reduction), 'creditedToCustomer' => Money::toString($excess)];
    }
}
