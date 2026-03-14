<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Core\Exception\ServiceException;
use App\Entity\Branch;
use App\Entity\SaleRecord;
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

        $cashSessionId = $resolver->resolveForSale($sale);

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

        $cashSessionId = $resolver->resolveForSale($sale);

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

        $resolver->resolveForSale($sale);
    }

    /**
     * Regression test: when $candidate was initialized to null (the bug), all `=== false` checks
     * were bypassed and validateCandidate() received null, causing a TypeError.
     * After the fix ($candidate = false), resolveForSale() must throw ServiceException instead.
     */
    public function testNullInitializationBugRegression(): void
    {
        $connection = $this->createMock(Connection::class);
        // legacyCashShiftId is provided but returns false; all fallbacks also return false
        $connection
            ->expects($this->exactly(3))
            ->method('fetchAssociative')
            ->willReturn(false);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-1', cashRegisterId: 5, issuedAt: '2026-02-28 10:00:00');

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches('/No se pudo resolver cash_session_id/');

        // Previously this would throw TypeError; now it must throw ServiceException
        $resolver->resolveForSale($sale);
    }

    /**
     * When REQUIRE_CASH_REGISTER=false and no cash_session exists in DB,
     * tryResolveForSale() must return null without throwing.
     */
    public function testTryResolveReturnsNullWhenNoSessionExists(): void
    {
        $connection = $this->createMock(Connection::class);
        // No legacy shift (null), so 3 queries: register+issuedAt, branch+issuedAt, latest
        $connection
            ->expects($this->exactly(3))
            ->method('fetchAssociative')
            ->willReturn(false);

        $resolver = new CashSessionResolver($connection);
        $sale = $this->buildSale(branchId: 'branch-1', cashRegisterId: 5, issuedAt: '2026-02-28 10:00:00');

        $result = $resolver->tryResolveForSale($sale);

        $this->assertNull($result);
    }

    /**
     * When the sale has no branch at all, tryResolveForSale() must still throw ServiceException
     * (infrastructure error — not a business scenario that can be silently skipped).
     */
    public function testTryResolveThrowsForUndeterminableBranch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAssociative');

        $resolver = new CashSessionResolver($connection);

        // Sale with no branch and no almacen
        $sale = new SaleRecord();
        $sale->setFecha(new \DateTimeImmutable('2026-02-28 10:00:00'));

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('sucursal no definida');

        $resolver->tryResolveForSale($sale);
    }

    private function buildSale(string $branchId, int $cashRegisterId, string $issuedAt): SaleRecord
    {
        $branch = new Branch();
        $this->setEntityId($branch, $branchId);

        $sale = new SaleRecord();
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
