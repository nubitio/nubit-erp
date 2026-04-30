<?php

declare(strict_types=1);

namespace App\Tests\AI\Context;

use App\AI\Context\BranchContextAdapter;
use App\Identity\Auth\BranchContextServiceInterface;
use PHPUnit\Framework\TestCase;

final class BranchContextAdapterTest extends TestCase
{
    public function testGetCurrentBranchIdDelegatesToService(): void
    {
        $service = $this->createStub(BranchContextServiceInterface::class);
        $service->method('getCurrentBranchId')->willReturn('branch-42');

        $adapter = new BranchContextAdapter($service);

        self::assertSame('branch-42', $adapter->getCurrentBranchId());
    }

    public function testGetCurrentBranchIdReturnsNullWhenServiceReturnsNull(): void
    {
        $service = $this->createStub(BranchContextServiceInterface::class);
        $service->method('getCurrentBranchId')->willReturn(null);

        $adapter = new BranchContextAdapter($service);

        self::assertNull($adapter->getCurrentBranchId());
    }
}
