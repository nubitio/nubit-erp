<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Core\Exception\ServiceException;
use App\Entity\Branch;
use App\Entity\Sale;
use App\State\Sale\CashSessionResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CashSessionResolverTest extends TestCase
{
    public function testResolvesByLegacyCashShiftWithinBranch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 101,
                'cash_register_id' => 7,
                'branch_id' => 'branch-1',
                'opened_at' => '2026-02-28 08:00:00',
                'closed_at' => null,
            ]);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-1', cashRegisterId: 7, issuedAt: '2026-02-28 10:00:00');

        $cashSessionId = $resolver->resolveForSale($sale, 55);

        $this->assertSame(101, $cashSessionId);
    }

    public function testFallsBackToRegisterWhenLegacyMappingIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                false,
                [
                    'id' => 202,
                    'cash_register_id' => 3,
                    'branch_id' => 'branch-2',
                    'opened_at' => '2026-02-28 09:00:00',
                    'closed_at' => null,
                ]
            );

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-2', cashRegisterId: 3, issuedAt: '2026-02-28 11:00:00');

        $cashSessionId = $resolver->resolveForSale($sale, 999);

        $this->assertSame(202, $cashSessionId);
    }

    public function testThrowsWhenResolvedSessionBelongsToAnotherBranch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 303,
                'cash_register_id' => 9,
                'branch_id' => 'branch-x',
                'opened_at' => '2026-02-28 08:00:00',
                'closed_at' => null,
            ]);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-y', cashRegisterId: 9, issuedAt: '2026-02-28 10:00:00');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('no pertenece a la sucursal');

        $resolver->resolveForSale($sale, 7);
    }

    private function buildSale(string $branchId, int $cashRegisterId, string $issuedAt): Sale
    {
        $branch = new Branch();
        $this->setEntityId($branch, $branchId);

        $sale = new Sale();
        $sale->setBranch($branch);
        $sale->setCajaId($cashRegisterId);
        $sale->setFecha(new \DateTimeImmutable($issuedAt));

        return $sale;
    }

    private function setEntityId(object $entity, string $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }
}
