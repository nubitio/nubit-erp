<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sale\Void;

use App\Billing\Sale\Void\ReceivableVoidReverser;
use App\Contacts\Entity\Customer;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Sales\Service\CustomerAccountLedger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ReceivableVoidReverserTest extends TestCase
{
    public function testNoOpWhenSaleIsNotReceivable(): void
    {
        $sale = new SaleRecord()->setId('sale-1');

        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries->expects(self::never())->method('hasChargeForSale');

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordChargeReversal');

        new ReceivableVoidReverser($entries, $ledger, new NullLogger())->reverse($sale);
    }

    public function testNoOpWhenSaleHasNoCustomer(): void
    {
        $sale = $this->makeReceivableSale('sale-2', customer: null);

        $entries = $this->createMock(CustomerAccountEntryRepository::class);
        $entries->expects(self::never())->method('hasChargeForSale');

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordChargeReversal');

        new ReceivableVoidReverser($entries, $ledger, new NullLogger())->reverse($sale);
    }

    public function testNoOpWhenNoChargeExistsForSale(): void
    {
        $sale = $this->makeReceivableSale('sale-3');

        $entries = $this->createStub(CustomerAccountEntryRepository::class);
        $entries->method('hasChargeForSale')->willReturn(false);

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordChargeReversal');

        new ReceivableVoidReverser($entries, $ledger, new NullLogger())->reverse($sale);
    }

    public function testNoOpWhenAlreadyReversed(): void
    {
        $sale = $this->makeReceivableSale('sale-4');

        $entries = $this->createStub(CustomerAccountEntryRepository::class);
        $entries->method('hasChargeForSale')->willReturn(true);
        $entries->method('hasReversalForSale')->willReturn(true);

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger->expects(self::never())->method('recordChargeReversal');

        new ReceivableVoidReverser($entries, $ledger, new NullLogger())->reverse($sale);
    }

    public function testRecordsReversalOnHappyPath(): void
    {
        $sale = $this->makeReceivableSale('sale-5');
        $sale->setSerie('B001');
        $sale->setNumero('7');

        $entries = $this->createStub(CustomerAccountEntryRepository::class);
        $entries->method('hasChargeForSale')->willReturn(true);
        $entries->method('hasReversalForSale')->willReturn(false);

        $ledger = $this->createMock(CustomerAccountLedger::class);
        $ledger
            ->expects(self::once())
            ->method('recordChargeReversal')
            ->with(
                self::equalTo(55),
                self::equalTo('sale-5'),
                self::isInstanceOf(\DateTimeImmutable::class),
                self::equalTo('100.00'),
                self::equalTo('PEN'),
                self::stringContains('B001-7'),
            );

        new ReceivableVoidReverser($entries, $ledger, new NullLogger())->reverse($sale);
    }

    private function makeReceivableSale(string $id, ?Customer $customer = new Customer()): SaleRecord
    {
        if ($customer !== null) {
            $prop = new \ReflectionClass($customer)->getProperty('id');
            $prop->setValue($customer, 55);
        }

        $sale = new SaleRecord()->setId($id);
        $sale->setCurrency('PEN');
        $sale->setCustomer($customer);
        $sale->setBalanceDue('100.00'); // flips isReceivableAccount() to true

        return $sale;
    }
}
