<?php

declare(strict_types=1);

namespace App\Tests\Efact\EventListener;

use App\Efact\EventListener\TenantListener;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TenantListenerE2eTest extends TestCase
{
    public function testActivatesPrivateContainerIpInE2eAndTestEnvironments(): void
    {
        foreach (['e2e', 'test'] as $environment) {
            $registry = $this->createMock(TenantRegistryInterface::class);
            $registry
                ->expects($this->once())
                ->method('getTenantByName')
                ->with('demo')
                ->willReturn(['id' => 1, 'name' => 'demo', 'status' => 'active']);

            $context = new TenantContext();
            $listener = new TenantListener(
                $this->createMock(TenantConnectionSwitcherInterface::class),
                $registry,
                $context,
                new NullLogger(),
                $environment,
            );
            $event = new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                Request::create('http://172.20.0.2/api/something'),
                HttpKernelInterface::MAIN_REQUEST,
            );

            $listener($event);

            static::assertSame('demo', $context->getTenantName());
        }
    }
}
