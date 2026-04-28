<?php

declare(strict_types=1);

namespace App\Tests\Contacts\Entity;

use App\Contacts\Entity\Supplier;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(Supplier::class)]
final class SupplierTest extends TestCase
{
    private Supplier $supplier;

    protected function setUp(): void
    {
        $this->supplier = new Supplier();
    }

    public function testGetIdReturnsNullBeforePersistence(): void
    {
        self::assertNull($this->supplier->getId());
    }

    public function testSetAndGetRuc(): void
    {
        $result = $this->supplier->setRuc('12345678901');

        self::assertSame('12345678901', $this->supplier->getRuc());
        self::assertInstanceOf(Supplier::class, $result);
    }

    public function testSetRucReturnsFluent(): void
    {
        self::assertSame($this->supplier, $this->supplier->setRuc('12345678901'));
    }

    public function testSetAndGetBusinessName(): void
    {
        $result = $this->supplier->setBusinessName('Proveedor S.A.C.');

        self::assertSame('Proveedor S.A.C.', $this->supplier->getBusinessName());
        self::assertInstanceOf(Supplier::class, $result);
    }

    public function testSetBusinessNameReturnsFluent(): void
    {
        self::assertSame($this->supplier, $this->supplier->setBusinessName('Proveedor'));
    }

    public function testSetAndGetFiscalAddress(): void
    {
        $result = $this->supplier->setFiscalAddress('Jr. Cusco 456');

        self::assertSame('Jr. Cusco 456', $this->supplier->getFiscalAddress());
        self::assertInstanceOf(Supplier::class, $result);
    }

    public function testGetFiscalAddressReturnsNullByDefault(): void
    {
        self::assertNull($this->supplier->getFiscalAddress());
    }

    public function testSetFiscalAddressReturnsFluent(): void
    {
        self::assertSame($this->supplier, $this->supplier->setFiscalAddress('Jr. Cusco 456'));
    }

    public function testSetAndGetPhone(): void
    {
        $result = $this->supplier->setPhone('+51912345678');

        self::assertSame('+51912345678', $this->supplier->getPhone());
        self::assertInstanceOf(Supplier::class, $result);
    }

    public function testSetPhoneToNull(): void
    {
        $this->supplier->setPhone('+51912345678');
        $this->supplier->setPhone(null);

        self::assertNull($this->supplier->getPhone());
    }

    public function testGetPhoneReturnsNullByDefault(): void
    {
        self::assertNull($this->supplier->getPhone());
    }

    public function testSetPhoneReturnsFluent(): void
    {
        self::assertSame($this->supplier, $this->supplier->setPhone(null));
    }
}
