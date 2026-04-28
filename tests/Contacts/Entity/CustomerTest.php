<?php

declare(strict_types=1);

namespace App\Tests\Contacts\Entity;

use App\Contacts\Entity\Customer;
use App\Inventory\Entity\PriceList;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(Customer::class)]
final class CustomerTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        $this->customer = new Customer();
    }

    public function testGetIdReturnsNullBeforePersistence(): void
    {
        self::assertNull($this->customer->getId());
    }

    public function testSetAndGetRuc(): void
    {
        $result = $this->customer->setRuc('12345678901');

        self::assertSame('12345678901', $this->customer->getRuc());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetRucReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setRuc('12345678'));
    }

    public function testSetAndGetBusinessName(): void
    {
        $result = $this->customer->setBusinessName('Acme Corp S.A.');

        self::assertSame('Acme Corp S.A.', $this->customer->getBusinessName());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetBusinessNameReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setBusinessName('Acme'));
    }

    public function testSetAndGetFiscalAddress(): void
    {
        $result = $this->customer->setFiscalAddress('Av. Lima 123');

        self::assertSame('Av. Lima 123', $this->customer->getFiscalAddress());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetFiscalAddressToNull(): void
    {
        $this->customer->setFiscalAddress('Av. Lima 123');
        $this->customer->setFiscalAddress(null);

        self::assertNull($this->customer->getFiscalAddress());
    }

    public function testGetFiscalAddressReturnsNullByDefault(): void
    {
        self::assertNull($this->customer->getFiscalAddress());
    }

    public function testSetFiscalAddressReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setFiscalAddress('Av. Lima 123'));
    }

    public function testSetAndGetPhone(): void
    {
        $result = $this->customer->setPhone('+51987654321');

        self::assertSame('+51987654321', $this->customer->getPhone());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetPhoneToNull(): void
    {
        $this->customer->setPhone('+51987654321');
        $this->customer->setPhone(null);

        self::assertNull($this->customer->getPhone());
    }

    public function testGetPhoneReturnsNullByDefault(): void
    {
        self::assertNull($this->customer->getPhone());
    }

    public function testSetPhoneReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setPhone(null));
    }

    public function testSetAndGetEmail(): void
    {
        $result = $this->customer->setEmail('contact@acme.com');

        self::assertSame('contact@acme.com', $this->customer->getEmail());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetEmailToNull(): void
    {
        $this->customer->setEmail('contact@acme.com');
        $this->customer->setEmail(null);

        self::assertNull($this->customer->getEmail());
    }

    public function testGetEmailReturnsNullByDefault(): void
    {
        self::assertNull($this->customer->getEmail());
    }

    public function testSetEmailReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setEmail(null));
    }

    public function testSetAndGetPriceList(): void
    {
        /** @var PriceList&Stub $priceList */
        $priceList = $this->createStub(PriceList::class);

        $result = $this->customer->setPriceList($priceList);

        self::assertSame($priceList, $this->customer->getPriceList());
        self::assertInstanceOf(Customer::class, $result);
    }

    public function testSetPriceListToNull(): void
    {
        /** @var PriceList&Stub $priceList */
        $priceList = $this->createStub(PriceList::class);

        $this->customer->setPriceList($priceList);
        $this->customer->setPriceList(null);

        self::assertNull($this->customer->getPriceList());
    }

    public function testGetPriceListReturnsNullByDefault(): void
    {
        self::assertNull($this->customer->getPriceList());
    }

    public function testSetPriceListReturnsFluent(): void
    {
        self::assertSame($this->customer, $this->customer->setPriceList(null));
    }
}
