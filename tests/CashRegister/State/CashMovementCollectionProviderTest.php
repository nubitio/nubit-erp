<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use App\CashRegister\State\CashMovementCollectionProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the 2026-08-19 fix: GET /api/cash-movements had no
 * explicit provider, so API Platform fell back to its default Doctrine ORM
 * collection provider — reading CashMovement's own mapped table, the
 * frozen legacy `cash_movement` (singular), instead of the live v2
 * `cash_movements` table. See ADR-004's amendment.
 */
final class CashMovementCollectionProviderTest extends TestCase
{
    public function testProvideReadsFromV2CashMovementsTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('WHERE s.branch_id = ?'), ['branch-a', 30, 0])
            ->willReturn([
                [
                    'id' => 900,
                    'cash_session_id' => null,
                    'movement_type' => 'CASH_IN',
                    'category_id' => null,
                    'amount' => '30.00',
                    'description' => 'Depósito',
                    'created_at' => '2026-08-19 09:00:00',
                ],
            ]);
        $connection->expects(self::once())->method('fetchOne')->with(self::stringContains('WHERE s.branch_id = ?'), ['branch-a'])->willReturn(1);

        $em = $this->createStub(EntityManagerInterface::class);
        $hydrator = new CashMovementHydrator($em, $connection);

        $provider = new CashMovementCollectionProvider($connection, $hydrator, $this->branchContext());

        $result = $provider->provide(new GetCollection(), [], []);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        $items = iterator_to_array($result);
        self::assertCount(1, $items);
        self::assertInstanceOf(CashMovement::class, $items[0]);
        self::assertSame(900, $items[0]->getId());
    }

    public function testProvideAppliesPageAndItemsPerPageFilters(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::anything(), ['branch-a', 10, 20], self::anything())
            ->willReturn([]);
        $connection->method('fetchOne')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $hydrator = new CashMovementHydrator($em, $connection);
        $provider = new CashMovementCollectionProvider($connection, $hydrator, $this->branchContext());

        $provider->provide(new GetCollection(), [], ['filters' => ['page' => 3, 'itemsPerPage' => 10]]);
    }
    private function branchContext(): BranchContextServiceInterface
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn('branch-a');

        return $context;
    }
}
