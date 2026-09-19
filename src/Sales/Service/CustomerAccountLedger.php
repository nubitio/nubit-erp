<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Efact\Domain\Money;
use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Repository\CustomerAccountEntryRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Append-only ledger of a customer's current account (#367).
 *
 * Every call appends one {@see CustomerAccountEntry} and stamps it with the
 * customer's running balance *after* the movement, so readers (the statement,
 * #384) never recompute in PHP. Entries are appended in call order; a
 * back-dated `occurredOn` still lands at the end of the running balance, it does
 * not rewrite history.
 *
 * This layer is additive: per-sale debt still lives on `SaleRecord.balanceDue`
 * and `sale_payments`. Wiring the collection flow (#381), advances (#382) and
 * the accounting entries (#383) onto this ledger is out of scope here.
 */
readonly class CustomerAccountLedger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerAccountEntryRepository $entries,
    ) {}

    /**
     * A credit sale increases what the customer owes.
     */
    public function recordCharge(
        int $customerId,
        string $saleId,
        DateTimeImmutable $occurredOn,
        string $amount,
        string $currency = 'PEN',
        ?string $description = null,
    ): CustomerAccountEntry {
        return $this->record(
            $customerId,
            CustomerAccountEntryType::CHARGE,
            $occurredOn,
            debit: $amount,
            credit: '0.00',
            currency: $currency,
            saleId: $saleId,
            source: 'sale',
            sourceId: $saleId,
            description: $description,
        );
    }

    /**
     * Money received and applied against outstanding sales.
     */
    public function recordPayment(
        int $customerId,
        DateTimeImmutable $occurredOn,
        string $amount,
        string $currency = 'PEN',
        ?string $saleId = null,
        ?string $source = null,
        ?string $sourceId = null,
        ?string $description = null,
    ): CustomerAccountEntry {
        return $this->record(
            $customerId,
            CustomerAccountEntryType::PAYMENT,
            $occurredOn,
            debit: '0.00',
            credit: $amount,
            currency: $currency,
            saleId: $saleId,
            source: $source,
            sourceId: $sourceId,
            description: $description,
        );
    }

    /**
     * Cancels the still-open CHARGE of a voided credit sale that had no
     * collections applied to it (mirrors {@see \App\Billing\Sale\Void\CashRefundRegistrar}
     * for the cash side). Reduces what the customer owes (credit).
     */
    public function recordChargeReversal(
        int $customerId,
        string $saleId,
        DateTimeImmutable $occurredOn,
        string $amount,
        string $currency = 'PEN',
        ?string $description = null,
    ): CustomerAccountEntry {
        return $this->record(
            $customerId,
            CustomerAccountEntryType::CHARGE_REVERSAL,
            $occurredOn,
            debit: '0.00',
            credit: $amount,
            currency: $currency,
            saleId: $saleId,
            source: 'sale_void',
            sourceId: $saleId,
            description: $description,
        );
    }

    /**
     * Money received with no target sale — a customer advance. It moves the
     * running balance into the customer's favour (credit). Applying it to a sale
     * later adds no further entry: the caller records a
     * `customer_payment_applications` row against this one and lowers the sale's
     * balance_due, leaving the net running balance unchanged (#382).
     */
    public function recordAdvanceReceived(
        int $customerId,
        DateTimeImmutable $occurredOn,
        string $amount,
        string $currency = 'PEN',
        ?string $source = null,
        ?string $sourceId = null,
        ?string $description = null,
    ): CustomerAccountEntry {
        return $this->record(
            $customerId,
            CustomerAccountEntryType::ADVANCE,
            $occurredOn,
            debit: '0.00',
            credit: $amount,
            currency: $currency,
            source: $source,
            sourceId: $sourceId,
            description: $description,
        );
    }

    /**
     * Append one movement. `debit` and `credit` are non-negative strings and
     * exactly one of them is greater than zero — an entry moves the balance in a
     * single direction.
     */
    public function record(
        int $customerId,
        CustomerAccountEntryType $entryType,
        DateTimeImmutable $occurredOn,
        string $debit,
        string $credit,
        string $currency = 'PEN',
        ?string $saleId = null,
        ?string $source = null,
        ?string $sourceId = null,
        ?string $description = null,
    ): CustomerAccountEntry {
        if ($customerId <= 0) {
            throw new InvalidArgumentException('A customer-account entry needs a customer id.');
        }

        $debitMoney = Money::money($debit);
        $creditMoney = Money::money($credit);

        if ($debitMoney->isNegative() || $creditMoney->isNegative()) {
            throw new InvalidArgumentException('Customer-account debit and credit cannot be negative.');
        }
        if ($debitMoney->isZero() && $creditMoney->isZero()) {
            throw new InvalidArgumentException('A customer-account entry must move either debit or credit.');
        }
        if ($debitMoney->isPositive() && $creditMoney->isPositive()) {
            throw new InvalidArgumentException('A customer-account entry moves the balance in one direction only.');
        }

        $previousBalance = $this->entries->currentBalance($customerId);
        $runningBalance = self::nextRunningBalance($previousBalance, $debit, $credit);

        $entry = new CustomerAccountEntry(
            customerId: $customerId,
            entryType: $entryType,
            occurredOn: $occurredOn,
            debit: Money::toString($debitMoney),
            credit: Money::toString($creditMoney),
            currency: $currency,
            runningBalance: $runningBalance,
            saleId: $saleId,
            source: $source,
            sourceId: $sourceId,
            description: $description,
        );

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    /**
     * Pure running-balance step: previous balance + debit - credit, at money
     * scale. Kept static so it can be folded over a sequence in tests without a
     * database.
     */
    public static function nextRunningBalance(string $previousBalance, string $debit, string $credit): string
    {
        return Money::toString(
            Money::money($previousBalance)->plus(Money::money($debit))->minus(Money::money($credit)),
        );
    }
}
