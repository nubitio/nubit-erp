<?php

namespace App\Tests\Position\Domain;

use App\Department\Domain\Department;
use App\Position\Domain\GetOnePositionUseCase;
use App\Position\Domain\Position;
use App\Position\Domain\PositionRepositoryInterface;
use PHPUnit\Framework\TestCase;

class GetOnePositionUseCaseTest extends TestCase
{
    public function testGetOnePosition(): void
    {
        // Create a mock repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);

        // Set up the expected return value of the mock repository
        $expectedPosition = new Position(1, new Department(1), 'Position 1', 'Description 1');
        $mockRepository->expects($this->once())
            ->method('getOne')
            ->with(1)
            ->willReturn($expectedPosition);

        // Instantiate the use case and call the invoke method
        $useCase = new GetOnePositionUseCase($mockRepository);
        $position = $useCase(1);

        // Assert that the position returned by the use case matches the expected position
        $this->assertEquals($expectedPosition, $position);
    }

    public function testGetOnePositionNotFound(): void
    {
        // Create a mock repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);

        // Set up the mock repository to return null when getOne is called
        $mockRepository->expects($this->once())
            ->method('getOne')
            ->with(1)
            ->willReturn(null);

        // Instantiate the use case and call the invoke method
        $useCase = new GetOnePositionUseCase($mockRepository);
        $position = $useCase(1);

        // Assert that the position returned by the use case is null
        $this->assertNull($position);
    }
}
