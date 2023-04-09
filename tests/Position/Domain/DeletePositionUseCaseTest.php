<?php

namespace App\Tests\Position\Domain;

use App\Position\Domain\DeletePositionUseCase;
use App\Position\Domain\PositionRepositoryInterface;
use PHPUnit\Framework\TestCase;

class DeletePositionUseCaseTest extends TestCase
{
    public function testDeletePosition(): void
    {
        // Mock the repository
        $mockRepository = $this->createMock(PositionRepositoryInterface::class);

        // Set up the expected method call on the mock repository
        $mockRepository->expects($this->once())
            ->method('delete')
            ->with(123);

        // Instantiate the use case and call the invoke method
        $useCase = new DeletePositionUseCase($mockRepository);
        $useCase(123);
    }
}