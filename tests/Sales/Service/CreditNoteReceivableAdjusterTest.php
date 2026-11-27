<?php

declare(strict_types=1);

namespace App\Tests\Sales\Service;

use App\Contacts\Entity\Customer;
use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Service\CreditNoteReceivableAdjuster;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** G-04 / D-01: a credit note lowers the open balance and leaves the rest as the customer's favour. */
#[AllowMockObjectsWithoutExpectations]
final class CreditNoteReceivableAdjusterTest extends TestCase
{
    private CustomerAccountLedger&MockObject $ledger;
    private CustomerAccountEntryRepository&MockObject $entries;

    protected function setUp(): void
    {
        $this->ledger = $this->createMock(CustomerAccountLedger::class);
        $this->entries = $this->createMock(CustomerAccountEntryRepository::class);
    }

    public function testACreditNoteOnAPendingInvoiceLowersTheBalance(): void
    {
        $sale = $this->sale('100.00', true);
        $this->ledger
            ->expects(self::once())
            ->method('record')
            ->with(
                7,
                CustomerAccountEntryType::CREDIT_NOTE,
                self::anything(),
                '0.00',
                '40.00',
                'PEN',
                's1',
                'credit_note',
                'n1',
            )
            ->willReturn($this->createStub(CustomerAccountEntry::class));
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');

        $result = $this->adjuster()->apply($sale, '40.00', 'n1', 'FC01-1', new DateTimeImmutable());

        self::assertSame('60.00', $sale->getBalanceDue());
        self::assertSame(['reduced' => '40.00', 'creditedToCustomer' => '0.00'], $result);
    }

    public function testAnExcessOverTheOpenBalanceBecomesAnAdvanceForTheCustomer(): void
    {
        $sale = $this->sale('30.00', true);
        $this->ledger->expects(self::once())->method('record')->willReturn(
            $this->createStub(CustomerAccountEntry::class),
        );
        $this->ledger
            ->expects(self::once())
            ->method('recordAdvanceReceived')
            ->with(7, self::anything(), '70.00', 'PEN', 'credit_note', 'n1:excess')
            ->willReturn($this->createStub(CustomerAccountEntry::class));

        $result = $this->adjuster()->apply($sale, '100.00', 'n1', 'FC01-1', new DateTimeImmutable());

        self::assertSame('0.00', $sale->getBalanceDue());
        self::assertSame(['reduced' => '30.00', 'creditedToCustomer' => '70.00'], $result);
    }

    public function testAFullyPaidInvoiceOnlyGeneratesTheAdvance(): void
    {
        $sale = $this->sale('0.00', true);
        $this->ledger->expects(self::never())->method('record');
        $this->ledger->expects(self::once())->method('recordAdvanceReceived')->willReturn(
            $this->createStub(CustomerAccountEntry::class),
        );

        $result = $this->adjuster()->apply($sale, '100.00', 'n1', 'FC01-1', new DateTimeImmutable());

        self::assertSame('100.00', $result['creditedToCustomer']);
    }

    public function testARetryOfTheSameCreditNoteDoesNothing(): void
    {
        $this->entries->method('hasEntryFromSource')->willReturn(true);
        $this->ledger->expects(self::never())->method('record');
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');
        $sale = $this->sale('100.00', true);

        $this->adjuster()->apply($sale, '40.00', 'n1', 'FC01-1', new DateTimeImmutable());

        self::assertSame('100.00', $sale->getBalanceDue());
    }

    public function testACashSaleIsLeftToItsOwnRefundFlow(): void
    {
        $this->ledger->expects(self::never())->method('record');
        $this->ledger->expects(self::never())->method('recordAdvanceReceived');
        $sale = $this->sale('0.00', false);

        $result = $this->adjuster()->apply($sale, '100.00', 'n1', 'FC01-1', new DateTimeImmutable());

        self::assertSame('0.00', $result['creditedToCustomer']);
    }

    private function adjuster(): CreditNoteReceivableAdjuster
    {
        return new CreditNoteReceivableAdjuster($this->createMock(EntityManagerInterface::class), $this->ledger, $this->entries);
    }

    private function sale(string $balanceDue, bool $receivable): SaleRecord
    {
        $customer = $this->createStub(Customer::class);
        $customer->method('getId')->willReturn(7);
        $sale = $this->createPartialMock(SaleRecord::class, ['getId', 'getCustomer']);
        $sale->method('getId')->willReturn('s1');
        $sale->method('getCustomer')->willReturn($customer);
        $sale->setReceivableAccount($receivable)->setCurrency('PEN');
        $sale->setBalanceDue($balanceDue);
        $sale->setReceivableAccount($receivable);

        return $sale;
    }
}
