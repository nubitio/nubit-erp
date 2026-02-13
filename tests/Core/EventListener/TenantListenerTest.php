<?php

declare(strict_types=1);

namespace App\Tests\Core\EventListener;

use App\Core\Context\TenantContext;
use App\Core\EventListener\TenantListener;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class TenantListenerTest extends TestCase
{
    private TenantConnectionSwitcher&MockObject $switcher;
    private TenantManager&MockObject $tenantManager;
    private TenantContext $tenantContext;
    private TenantListener $listener;

    protected function setUp(): void
    {
        $this->switcher = $this->createMock(TenantConnectionSwitcher::class);
        $this->tenantManager = $this->createMock(TenantManager::class);
        $this->tenantContext = new TenantContext();

        $this->listener = new TenantListener(
            $this->switcher,
            $this->tenantManager,
            $this->tenantContext,
            new NullLogger(),
        );
    }

    private function createEvent(string $host, string $path = '/api/something'): RequestEvent
    {
        $request = Request::create('https://' . $host . $path);
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testSkipsAdminTenantsPath(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('acme.example.com', '/admin/tenants'));
    }

    public function testSkipsRegisterPath(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('app.example.com', '/register/new-tenant'));
    }

    public function testSkipsAccountPath(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('app.example.com', '/account/dashboard'));
    }

    public function testSkipsLocalhostSubdomain(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('localhost'));
    }

    public function testSkipsDevSubdomain(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('dev.example.com'));
    }

    public function testSkipsStgSubdomain(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('stg.example.com'));
    }

    public function testSkipsPrivateIp(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('192.168.1.1'));
    }

    public function testSkipsLoopbackIp(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('127.0.0.1'));
    }

    public function testResolvesSubdomainTenant(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];

        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')
            ->with('acme')
            ->willReturn($tenantData);

        $this->switcher->expects($this->once())
            ->method('switchConnection')
            ->with('acme');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertSame(1, $this->tenantContext->getTenantId());
        $this->assertSame('acme', $this->tenantContext->getTenantName());
    }

    public function testResolvesByDomainFirst(): void
    {
        $tenantData = ['id' => 2, 'name' => 'corp', 'connection' => 'corp'];

        $this->tenantManager->method('getTenantByDomain')
            ->with('custom.corp.com')
            ->willReturn($tenantData);

        $this->tenantManager->expects($this->never())->method('getTenantByName');

        $this->switcher->expects($this->once())
            ->method('switchConnection')
            ->with('corp');

        ($this->listener)($this->createEvent('custom.corp.com'));

        $this->assertSame(2, $this->tenantContext->getTenantId());
        $this->assertSame('corp', $this->tenantContext->getTenantName());
    }

    public function testSuspendedTenantIsBlocked(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme', 'status' => 'suspended'];

        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn($tenantData);

        $this->switcher->expects($this->never())->method('switchConnection');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Service temporarily unavailable');

        ($this->listener)($this->createEvent('acme.example.com'));
    }

    public function testActiveTenantIsAllowed(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme', 'status' => 'active'];

        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertSame('acme', $this->tenantContext->getTenantName());
    }

    public function testErrorDoesNotLeakTenantName(): void
    {
        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')
            ->willThrowException(new \RuntimeException('Tenant not found'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid request');

        ($this->listener)($this->createEvent('secret-tenant.example.com'));
    }

    public function testSetsRequestIdFromHeader(): void
    {
        $request = Request::create('https://acme.example.com/api/test');
        $request->headers->set('X-Request-Id', 'custom-req-id');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];
        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($event);

        $this->assertSame('custom-req-id', $this->tenantContext->getRequestId());
    }

    public function testGeneratesRequestIdWhenMissing(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];
        $this->tenantManager->method('getTenantByDomain')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertNotNull($this->tenantContext->getRequestId());
        $this->assertNotEmpty($this->tenantContext->getRequestId());
    }
}
