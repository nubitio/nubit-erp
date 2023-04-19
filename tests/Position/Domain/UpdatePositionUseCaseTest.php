<?php

namespace App\Tests\Position\Domain;

use App\Department\Domain\Department;
use App\Position\Domain\Position;
use App\Position\Domain\PositionRepositoryInterface;
use App\Position\Domain\UpdatePositionUseCase;
use PHPUnit\Framework\TestCase;

class UpdatePositionUseCaseTest extends TestCase
{
    public function testUpdatePosition(): void
    {
        // Set up the mock repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);

        // Create a position to update
        $position = new Position(1, new Department(1), 'Position 1', 'Description 1');

        // Set up the expected return value of the mock repository
        $mockRepository->expects($this->once())
            ->method('update')
            ->with($position)
            ->willReturn($position);

        // Instantiate the use case and call the invoke method
        $useCase = new UpdatePositionUseCase($mockRepository);
        $updatedPosition = $useCase($position);

        // Assert that the updated position returned by the use case is the same as the original position
        $this->assertSame($position, $updatedPosition);
    }
}
