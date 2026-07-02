<?php

declare(strict_types=1);

namespace App\Tests\Organization\Entity;

use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use PHPUnit\Framework\TestCase;

final class BranchTest extends TestCase
{
    public function testSetAndGetName(): void
    {
        $branch = new Branch();
        $result = $branch->setName('Main Store');

        self::assertSame('Main Store', $branch->getName());
        self::assertSame($branch, $result); // fluent
    }

    public function testSetAndGetAddress(): void
    {
        $branch = new Branch()
            ->setName('B')
            ->setAddress('Av. Larco 123, Lima');

        self::assertSame('Av. Larco 123, Lima', $branch->getAddress());
    }

    public function testSetAndGetPhone(): void
    {
        $branch = new Branch()
            ->setName('B')
            ->setPhone('01-234-5678');

        self::assertSame('01-234-5678', $branch->getPhone());
    }

    public function testSetAndGetCompany(): void
    {
        $company = new Company();
        $branch = new Branch()
            ->setName('B')
            ->setCompany($company);

        self::assertSame($company, $branch->getCompany());
    }

    public function testAddressDefaultsToNull(): void
    {
        $branch = new Branch()->setName('B');
        self::assertNull($branch->getAddress());
    }

    public function testPhoneDefaultsToNull(): void
    {
        $branch = new Branch()->setName('B');
        self::assertNull($branch->getPhone());
    }

    public function testWarehousesCollectionIsEmptyByDefault(): void
    {
        $branch = new Branch()->setName('B');
        self::assertTrue($branch->getWarehouses()->isEmpty());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        self::assertNull(
            new Branch()
                ->setName('B')
                ->getId(),
        );
    }
}
