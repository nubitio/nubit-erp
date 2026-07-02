<?php

declare(strict_types=1);

namespace App\Tests\Inventory\State;

use ApiPlatform\Metadata\Post;
use App\Inventory\Dto\PriceItemDto;
use App\Inventory\Dto\PricesDto;
use App\Inventory\State\PriceListProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException as DbalException;
use PHPUnit\Framework\TestCase;

final class PriceListProcessorTest extends TestCase
{
    private function makeDto(): PricesDto
    {
        return new PricesDto(priceListId: 3, prices: [
            new PriceItemDto('prod-1', '29.9000'),
            new PriceItemDto('prod-2', '49.9000'),
        ]);
    }

    public function testProcessInsertsAllPricesAndCommits(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->expects(self::exactly(2))->method('executeStatement');
        $conn->expects(self::once())->method('commit');
        $conn->expects(self::never())->method('rollBack');

        $processor = new PriceListProcessor($conn);
        $response = $processor->process($this->makeDto(), new Post());

        self::assertTrue($response->success);
        self::assertStringContainsString('correctamente', $response->message);
    }

    public function testProcessRollsBackOnDbalException(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->method('executeStatement')->willThrowException(new DbalException('DB error'));
        $conn->expects(self::never())->method('commit');
        $conn->expects(self::once())->method('rollBack');

        $processor = new PriceListProcessor($conn);
        $response = $processor->process($this->makeDto(), new Post());

        self::assertFalse($response->success);
        self::assertStringContainsString('Error', $response->message);
    }

    public function testProcessWithEmptyPricesListCommitsWithoutStatements(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('beginTransaction');
        $conn->expects(self::never())->method('executeStatement');
        $conn->expects(self::once())->method('commit');

        $dto = new PricesDto(priceListId: 1, prices: []);
        $processor = new PriceListProcessor($conn);
        $response = $processor->process($dto, new Post());

        self::assertTrue($response->success);
    }
}
