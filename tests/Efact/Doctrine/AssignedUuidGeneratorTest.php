<?php

declare(strict_types=1);

namespace App\Tests\Efact\Doctrine;

use App\Efact\Doctrine\AssignedUuidGenerator;
use App\Purchasing\Entity\Purchase;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AssignedUuidGeneratorTest extends TestCase
{
    public function testKeepsPreAssignedSaleId(): void
    {
        $sale = new SaleRecord()->setId('01aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee');
        $generated = new AssignedUuidGenerator()->generateId($this->createStub(EntityManagerInterface::class), $sale);

        self::assertSame('01aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee', $generated);
    }

    public function testKeepsPreAssignedPurchaseId(): void
    {
        $purchase = new Purchase()->setId('01bbbbbbbb-cccc-7ddd-8eee-ffffffffffff');
        $generated = new AssignedUuidGenerator()->generateId(
            $this->createStub(EntityManagerInterface::class),
            $purchase,
        );

        self::assertSame('01bbbbbbbb-cccc-7ddd-8eee-ffffffffffff', $generated);
    }

    public function testMintsWhenIdIsMissing(): void
    {
        $generated = new AssignedUuidGenerator()->generateId(
            $this->createStub(EntityManagerInterface::class),
            new SaleRecord(),
        );

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $generated,
        );
    }
}
