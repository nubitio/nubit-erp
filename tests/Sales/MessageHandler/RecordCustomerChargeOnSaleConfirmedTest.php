<?php

declare(strict_types=1);

namespace App\Tests\Sales\MessageHandler;

use App\Contacts\Entity\Customer;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\SaleRecord;
use App\Sales\Event\SaleConfirmed;
use App\Sales\MessageHandler\RecordCustomerChargeOnSaleConfirmed;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class RecordCustomerChargeOnSaleConfirmedTest extends TestCase
{
    public function testAppendsAChargeForACreditSaleWithACustomer(): void
    {
        $sale = $this->sale(
            balanceDue: '118.00',
            grandTotal: '118.00',
            customerId: 55,
            issuedDate: new DateTimeImmutable('2026-03-04'),
            serie: 'F001',
            numero: '9',
            currency: 'PEN',
        );

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger
            ->expects(self::once())
            ->method('recordCharge')
            ->with(55, 'sale-1', new DateTimeImmutable('2026-03-04'), '118.00', 'PEN', 'F001-9')
            ->willReturn($this->createStub(CustomerAccountEntry::class));

        $this->handle($sale, alreadyCharged: false, ledger: $ledger);
    }

    public function testSkipsWhenTheSaleDoesNotHitTheCustomerAccount(): void
    {
        $sale = $this->sale(balanceDue: null, grandTotal: '50.00', customerId: 55);

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordCharge');

        $this->handle($sale, alreadyCharged: false, ledger: $ledger);
    }

    public function testSkipsWhenThereIsNoCustomer(): void
    {
        $sale = $this->sale(balanceDue: '10.00', grandTotal: '10.00', customerId: null);

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordCharge');

        $this->handle($sale, alreadyCharged: false, ledger: $ledger);
    }

    public function testIsIdempotentPerSale(): void
    {
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordCharge');

        // alreadyCharged short-circuits before the sale is even loaded.
        $this->handle(null, alreadyCharged: true, ledger: $ledger);
    }

    public function testSkipsWhenTheSaleIsGone(): void
    {
        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordCharge');

        $this->handle(null, alreadyCharged: false, ledger: $ledger);
    }

    private function handle(?SaleRecord $sale, bool $alreadyCharged, CustomerAccountLedger $ledger): void
    {
        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries->method('hasChargeForSale')->with('sale-1')->willReturn($alreadyCharged);

        $sales = $this->createMock(SaleRecordRepository::class);
        $sales->method('findByAnyId')->with('sale-1')->willReturn($sale);

        (new RecordCustomerChargeOnSaleConfirmed($sales, $entries, $ledger))(new SaleConfirmed(
            saleId: 'sale-1',
            warehouseId: 'w1',
            lines: [],
        ));
    }

    private function sale(
        ?string $balanceDue,
        string $grandTotal,
        ?int $customerId,
        ?DateTimeImmutable $issuedDate = null,
        ?string $serie = null,
        ?string $numero = null,
        string $currency = 'PEN',
    ): SaleRecord {
        $sale = new SaleRecord()
            ->setGrandTotal($grandTotal)
            ->setCurrency($currency)
            ->setIssuedDate($issuedDate)
            ->setSerie($serie)
            ->setNumero($numero);

        if ($balanceDue !== null) {
            $sale->setBalanceDue($balanceDue);
        }

        if ($customerId !== null) {
            $customer = new Customer();
            new \ReflectionProperty(Customer::class, 'id')->setValue($customer, $customerId);
            $sale->setCustomer($customer);
        }

        return $sale;
    }
}
