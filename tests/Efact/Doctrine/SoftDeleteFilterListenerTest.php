<?php

declare(strict_types=1);

namespace App\Tests\Efact\Doctrine;

use App\Efact\Doctrine\SoftDeleteFilterListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SoftDeleteFilterListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private FilterCollection&MockObject $filters;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filters = $this->createMock(FilterCollection::class);
    }

    public function testEnablesSoftDeleteFilterOnMainRequest(): void
    {
        $this->entityManager->expects(self::once())
            ->method('getFilters')
            ->willReturn($this->filters);

        $this->filters->expects(self::once())
            ->method('isEnabled')
            ->with('soft_delete')
            ->willReturn(false);

        $this->filters->expects(self::once())
            ->method('enable')
            ->with('soft_delete');

        $listener = new SoftDeleteFilterListener($this->entityManager);
        $listener($this->createRequestEvent(HttpKernelInterface::MAIN_REQUEST));
    }

    public function testSkipsSubRequests(): void
    {
        $this->entityManager->expects(self::never())->method('getFilters');

        $listener = new SoftDeleteFilterListener($this->entityManager);
        $listener($this->createRequestEvent(HttpKernelInterface::SUB_REQUEST));
    }

    public function testDoesNotEnableFilterTwice(): void
    {
        $this->entityManager->expects(self::once())
            ->method('getFilters')
            ->willReturn($this->filters);

        $this->filters->expects(self::once())
            ->method('isEnabled')
            ->with('soft_delete')
            ->willReturn(true);

        $this->filters->expects(self::never())->method('enable');

        $listener = new SoftDeleteFilterListener($this->entityManager);
        $listener($this->createRequestEvent(HttpKernelInterface::MAIN_REQUEST));
    }

    private function createRequestEvent(int $requestType): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/api/purchases'),
            $requestType,
        );
    }
}
