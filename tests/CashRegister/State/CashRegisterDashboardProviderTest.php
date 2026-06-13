<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\GetCollection;
use App\CashRegister\Dto\CashRegisterDashboardDto;
use App\CashRegister\State\CashRegisterDashboardProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CashRegisterDashboardProviderTest extends TestCase
{
    /** @return array<string, mixed>[] */
    private static function dbRows(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Caja 1',
                'cashSessionId' => '42',
                'openedAt' => '2026-04-30 08:00:00',
                'closedAt' => null,
                'cashier' => 'juan',
                'balance' => '500.00',
                'status' => 'open',
                'today_sales' => '1200.50',
            ],
            [
                'id' => 2,
                'name' => 'Caja 2',
                'cashSessionId' => null,
                'openedAt' => null,
                'closedAt' => null,
                'cashier' => null,
                'balance' => '0.00',
                'status' => 'closed',
                'today_sales' => '0',
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function makeProvider(
        ?string $branchId = 'branch-1',
        array $rows = [],
    ): CashRegisterDashboardProvider {
        $branchCtx = $this->createStub(BranchContextServiceInterface::class);
        $branchCtx->method('getCurrentBranchId')->willReturn($branchId);

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn($rows);

        return new CashRegisterDashboardProvider($conn, $branchCtx);
    }

    // ── No branch ─────────────────────────────────────────────────────────────

    public function testProvideReturnsEmptyArrayWhenNoBranch(): void
    {
        $provider = $this->makeProvider(branchId: null);
        $result = $provider->provide(new GetCollection());

        self::assertSame([], $result);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function testProvideReturnsMappedDtos(): void
    {
        $provider = $this->makeProvider(rows: self::dbRows());
        $result = $provider->provide(new GetCollection());

        self::assertCount(2, $result);
        self::assertInstanceOf(CashRegisterDashboardDto::class, $result[0]);
    }

    public function testProvideMapsCashRegisterFields(): void
    {
        $provider = $this->makeProvider(rows: self::dbRows());
        $result = $provider->provide(new GetCollection());

        $dto = $result[0];
        self::assertSame(1, $dto->id);
        self::assertSame('Caja 1', $dto->name);
        self::assertSame(42, $dto->cashShiftId);
        self::assertNotNull($dto->openedAt);
        self::assertNull($dto->closedAt);
        self::assertSame('juan', $dto->cashier);
        self::assertEqualsWithDelta(500.0, $dto->balance ?? 0, 0.01);
        self::assertSame('open', $dto->status);
        self::assertSame('1200.50', $dto->todaySales);
    }

    public function testProvideMapsClosedRegisterWithNullFields(): void
    {
        $provider = $this->makeProvider(rows: self::dbRows());
        $result = $provider->provide(new GetCollection());

        $dto = $result[1];
        self::assertSame(2, $dto->id);
        self::assertNull($dto->cashShiftId);
        self::assertNull($dto->openedAt);
        self::assertNull($dto->cashier);
        self::assertSame('closed', $dto->status);
        self::assertSame('0.00', $dto->todaySales);
    }

    public function testProvideReturnsEmptyArrayWhenNoRegisters(): void
    {
        $provider = $this->makeProvider(rows: []);
        $result = $provider->provide(new GetCollection());

        self::assertSame([], $result);
    }
}
