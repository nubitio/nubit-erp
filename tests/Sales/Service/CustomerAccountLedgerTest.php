<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerAccountLedgerTest extends TestCase
{
    /**
     * The running balance is stamped by folding debit - credit onto whatever
     * balance the customer already had. This is the acceptance criterion of
     * #380: N charges and M payments in order produce the right running balance
     * on every row.
     */
    public function testRunningBalanceWalksChargesAndPaymentsInAppendOrder(): void
    {
        [$ledger, $persisted] = $this->makeLedger();

        $day = new DateTimeImmutable('2026-03-01');

        $ledger->recordCharge(42, 's1', $day, '500.00');
        $ledger->recordCharge(42, 's2', $day, '300.00');
        $ledger->recordPayment(42, $day, '450.00', saleId: 's1');
        $ledger->recordCharge(42, 's3', $day, '120.00');
        $ledger->recordPayment(42, $day, '470.00');

        $balances = array_map(
            static fn(CustomerAccountEntry $e): string => $e->getRunningBalance(),
            $persisted->entries,
        );

        // 0 +500 -> 500; +300 -> 800; -450 -> 350; +120 -> 470; -470 -> 0
        self::assertSame(['500.00', '800.00', '350.00', '470.00', '0.00'], $balances);
    }

    public function testRunningBalanceCanGoNegativeWhenPaymentsRunAhead(): void
    {
        [$ledger, $persisted] = $this->makeLedger();
        $day = new DateTimeImmutable('2026-03-01');

        $ledger->recordCharge(7, 's1', $day, '100.00');
        $ledger->recordPayment(7, $day, '250.00');

        self::assertSame('-150.00', $persisted->last()->getRunningBalance());
    }

    public function testChargeIsADebitAndTagsTheSale(): void
    {
        [$ledger, $persisted] = $this->makeLedger();

        $entry = $ledger->recordCharge(1, 'sale-abc', new DateTimeImmutable('2026-03-02'), '200.00', 'USD', 'F001-9');

        self::assertSame(CustomerAccountEntryType::CHARGE, $entry->getEntryType());
        self::assertSame('200.00', $entry->getDebit());
        self::assertSame('0.00', $entry->getCredit());
        self::assertSame('sale-abc', $entry->getSaleId());
        self::assertSame('USD', $entry->getCurrency());
        self::assertSame('F001-9', $entry->getDescription());
        self::assertSame($entry, $persisted->last());
    }

    public function testChargeReversalIsACreditTaggedAsSaleVoid(): void
    {
        [$ledger] = $this->makeLedger();

        $entry = $ledger->recordChargeReversal(
            1,
            'sale-abc',
            new DateTimeImmutable('2026-03-02'),
            '200.00',
            'PEN',
            'Anulación de F001-9',
        );

        self::assertSame(CustomerAccountEntryType::CHARGE_REVERSAL, $entry->getEntryType());
        self::assertSame('0.00', $entry->getDebit());
        self::assertSame('200.00', $entry->getCredit());
        self::assertSame('sale-abc', $entry->getSaleId());
        self::assertSame('sale_void', $entry->getSource());
        self::assertSame('sale-abc', $entry->getSourceId());
    }

    public function testChargeReversalOffsetsThePriorCharge(): void
    {
        [$ledger, $persisted] = $this->makeLedger();
        $day = new DateTimeImmutable('2026-03-01');

        $ledger->recordCharge(9, 'sale-x', $day, '150.00');
        $ledger->recordChargeReversal(9, 'sale-x', $day, '150.00');

        self::assertSame('0.00', $persisted->last()->getRunningBalance());
    }

    public function testAdvanceReceivedIsACreditWithNoSale(): void
    {
        [$ledger] = $this->makeLedger();

        $entry = $ledger->recordAdvanceReceived(3, new DateTimeImmutable('2026-03-02'), '900.00');

        self::assertSame(CustomerAccountEntryType::ADVANCE, $entry->getEntryType());
        self::assertSame('0.00', $entry->getDebit());
        self::assertSame('900.00', $entry->getCredit());
        self::assertNull($entry->getSaleId());
        self::assertSame('-900.00', $entry->getRunningBalance());
    }

    #[DataProvider('invalidAmounts')]
    public function testRecordRejectsAmountsThatAreNotSingleDirectionAndNonNegative(string $debit, string $credit): void
    {
        [$ledger] = $this->makeLedger();

        $this->expectException(InvalidArgumentException::class);
        $ledger->record(
            1,
            CustomerAccountEntryType::CHARGE,
            new DateTimeImmutable('2026-03-01'),
            debit: $debit,
            credit: $credit,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidAmounts(): iterable
    {
        yield 'both zero' => ['0.00', '0.00'];
        yield 'both positive' => ['10.00', '5.00'];
        yield 'negative debit' => ['-10.00', '0.00'];
        yield 'negative credit' => ['0.00', '-10.00'];
    }

    public function testRecordRejectsMissingCustomer(): void
    {
        [$ledger] = $this->makeLedger();

        $this->expectException(InvalidArgumentException::class);
        $ledger->record(0, CustomerAccountEntryType::CHARGE, new DateTimeImmutable('2026-03-01'), '10.00', '0.00');
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function balanceSteps(): iterable
    {
        yield 'first charge from zero' => ['0.00', '500.00', '0.00', '500.00'];
        yield 'payment reduces balance' => ['500.00', '0.00', '200.00', '300.00'];
        yield 'payment past balance goes negative' => ['100.00', '0.00', '250.00', '-150.00'];
        yield 'rounding stays at money scale' => ['0.01', '0.02', '0.00', '0.03'];
    }

    #[DataProvider('balanceSteps')]
    public function testNextRunningBalanceIsPureDebitMinusCredit(
        string $previous,
        string $debit,
        string $credit,
        string $expected,
    ): void {
        self::assertSame($expected, CustomerAccountLedger::nextRunningBalance($previous, $debit, $credit));
    }

    /**
     * @return array{0: CustomerAccountLedger, 1: LedgerEntrySink}
     */
    private function makeLedger(): array
    {
        $sink = new LedgerEntrySink();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use ($sink): void {
                if ($entity instanceof CustomerAccountEntry) {
                    $sink->entries[] = $entity;
                }
            });

        $repository = $this->createMock(CustomerAccountEntryRepository::class);
        $repository
            ->method('currentBalance')
            ->willReturnCallback(static function (int $customerId) use ($sink): string {
                for ($i = count($sink->entries) - 1; $i >= 0; $i--) {
                    if ($sink->entries[$i]->getCustomerId() === $customerId) {
                        return $sink->entries[$i]->getRunningBalance();
                    }
                }

                return '0.00';
            });

        return [new CustomerAccountLedger($entityManager, $repository), $sink];
    }
}

/**
 * Captures the {@see CustomerAccountEntry} rows the ledger would persist, so a
 * unit test can assert running balances without a database.
 */
final class LedgerEntrySink
{
    /** @var list<CustomerAccountEntry> */
    public array $entries = [];

    public function last(): CustomerAccountEntry
    {
        return $this->entries[array_key_last($this->entries)];
    }
}
