<?php

namespace App\Tests\Position\Domain;

use App\Department\Domain\Department;
use App\Position\Domain\CreatePositionUseCase;
use App\Position\Domain\Position;
use App\Position\Domain\PositionRepositoryInterface;
use PHPUnit\Framework\TestCase;

class CreatePositionUseCaseTest extends TestCase
{
    public function testCreatePosition(): void
    {
        // Mock the repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);
        $department = new Department(1);

        // Set up the expected return value of the mock repository
        $expectedPosition = new Position(1, $department, 'Position 1', 'Description 1');
        $mockRepository->expects($this->once())
            ->method('insert')
            ->with($expectedPosition)
            ->willReturn($expectedPosition);

        // Instantiate the use case and call the invoke method
        $useCase = new CreatePositionUseCase($mockRepository);
        $position = $useCase(new Position(1, $department, 'Position 1', 'Description 1'));

        // Assert that the position returned by the use case is the expected one
        $this->assertSame($expectedPosition, $position);
    }
}