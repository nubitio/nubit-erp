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
        static::assertSame(['500.00', '800.00', '350.00', '470.00', '0.00'], $balances);
    }

    public function testRunningBalanceCanGoNegativeWhenPaymentsRunAhead(): void
    {
        [$ledger, $persisted] = $this->makeLedger();
        $day = new DateTimeImmutable('2026-03-01');

        $ledger->recordCharge(7, 's1', $day, '100.00');
        $ledger->recordPayment(7, $day, '250.00');

        static::assertSame('-150.00', $persisted->last()->getRunningBalance());
    }

    public function testChargeIsADebitAndTagsTheSale(): void
    {
        [$ledger, $persisted] = $this->makeLedger();

        $entry = $ledger->recordCharge(1, 'sale-abc', new DateTimeImmutable('2026-03-02'), '200.00', 'USD', 'F001-9');

        static::assertSame(CustomerAccountEntryType::CHARGE, $entry->getEntryType());
        static::assertSame('200.00', $entry->getDebit());
        static::assertSame('0.00', $entry->getCredit());
        static::assertSame('sale-abc', $entry->getSaleId());
        static::assertSame('USD', $entry->getCurrency());
        static::assertSame('F001-9', $entry->getDescription());
        static::assertSame($entry, $persisted->last());
    }

    public function testAdvanceReceivedIsACreditWithNoSale(): void
    {
        [$ledger] = $this->makeLedger();

        $entry = $ledger->recordAdvanceReceived(3, new DateTimeImmutable('2026-03-02'), '900.00');

        static::assertSame(CustomerAccountEntryType::ADVANCE, $entry->getEntryType());
        static::assertSame('0.00', $entry->getDebit());
        static::assertSame('900.00', $entry->getCredit());
        static::assertNull($entry->getSaleId());
        static::assertSame('-900.00', $entry->getRunningBalance());
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
        static::assertSame($expected, CustomerAccountLedger::nextRunningBalance($previous, $debit, $credit));
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
