<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\State\CashRegisterSummaryProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CashRegisterSummaryProviderTest extends TestCase
{
    public function testWithoutBranchEverythingIsZero(): void
    {
        $provider = new CashRegisterSummaryProvider($this->createStub(Connection::class), $this->branch(null));

        $dto = $provider->provide(new Get());

        self::assertSame('0.00', $dto->salesToday);
        self::assertSame(0, $dto->registersWithShiftToday);
        self::assertSame('0.00', $dto->closureDifference7Days);
        self::assertSame(0, $dto->shortageShifts7Days);
    }

    public function testMapsTheFiguresOfTheBranch(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('14265.8', 3, 38);
        $connection->method('fetchAssociative')->willReturn(['net' => '-12.5', 'shortages' => '2']);

        $dto = new CashRegisterSummaryProvider($connection, $this->branch('branch-1'))->provide(new Get());

        self::assertSame('14265.80', $dto->salesToday);
        self::assertSame(3, $dto->registersWithShiftToday);
        self::assertSame('-12.50', $dto->closureDifference7Days);
        self::assertSame(2, $dto->shortageShifts7Days);
        self::assertSame(38, $dto->shiftsTotal);
    }

    private function branch(?string $id): BranchContextServiceInterface
    {
        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn($id);

        return $branch;
    }
}
