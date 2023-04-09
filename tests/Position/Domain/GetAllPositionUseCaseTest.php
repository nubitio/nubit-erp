<?php

namespace App\Tests\Position\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Position\Domain\GetAllPositionUseCase;
use App\Position\Domain\Position;
use App\Position\Domain\PositionRepositoryInterface;
use App\Position\Infrastructure\PositionEntity;
use PHPUnit\Framework\TestCase;

class GetAllPositionUseCaseTest extends TestCase
{
    public function testGetAllPositions(): void
    {
        // Mock the repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);

        // Set up the expected return value of the mock repository
        $expectedPositions = [
            new Position(1, 'Position 1', 'Description 1'),
            new Position(2, 'Position 2', 'Description 2'),
            new Position(3, 'Position 3', 'Description 3'),
        ];
        $mockPaginator = $this->createMock(PaginatorInterface::class);
        $mockPaginator->expects($this->once())
            ->method('getTotalItems')
            ->willReturn(3.0);
        $mockPaginator->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator($expectedPositions));
        $mockRepository->expects($this->once())
            ->method('getAll')
            ->with($this->isInstanceOf(Options::class))
            ->willReturn($mockPaginator);

        // Instantiate the use case and call the invoke method
        $useCase = new GetAllPositionUseCase($mockRepository);
        $paginator = $useCase(new Options(
            entity: PositionEntity::class,
            mapping: Position::class,
            page: 1,
            rows: 10
        ));

        // Assert that the paginator returned by the use case contains the expected positions
        $this->assertSame(count($expectedPositions), intval($paginator->getTotalItems()));
        $this->assertSame($expectedPositions, iterator_to_array($paginator->getIterator()));
    }
}

