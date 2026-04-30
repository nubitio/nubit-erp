<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\VoucherSeries;
use App\Catalog\Entity\VoucherType;
use App\Organization\Entity\Branch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VoucherSeries::class)]
final class VoucherSeriesTest extends TestCase
{
    public function testGetIdReturnsNullBeforePersistence(): void
    {
        $entity = new VoucherSeries();

        self::assertNull($entity->getId());
    }

    public function testBranchRoundTrip(): void
    {
        $branch = $this->createStub(Branch::class);

        $entity = new VoucherSeries();
        $entity->setBranch($branch);

        self::assertSame($branch, $entity->getBranch());
    }

    public function testVoucherTypeRoundTrip(): void
    {
        $voucherType = $this->createStub(VoucherType::class);

        $entity = new VoucherSeries();
        $entity->setVoucherType($voucherType);

        self::assertSame($voucherType, $entity->getVoucherType());
    }

    public function testSeriesRoundTrip(): void
    {
        $entity = new VoucherSeries();
        $entity->setSeries('F001');

        self::assertSame('F001', $entity->getSeries());
    }

    public function testAssociatedSeriesRoundTrip(): void
    {
        $entity = new VoucherSeries();
        $entity->setAssociatedSeries('B001');

        self::assertSame('B001', $entity->getAssociatedSeries());
    }

    public function testAssociatedSeriesDefaultsToNull(): void
    {
        $entity = new VoucherSeries();

        self::assertNull($entity->getAssociatedSeries());
    }

    public function testAssociatedSeriesCanBeSetToNull(): void
    {
        $entity = new VoucherSeries();
        $entity->setAssociatedSeries('B001');
        $entity->setAssociatedSeries(null);

        self::assertNull($entity->getAssociatedSeries());
    }
}
