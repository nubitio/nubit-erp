<?php

declare(strict_types=1);

namespace App\Tests\Efact\EventListener;

use App\Efact\EventListener\TenantListener;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[AllowMockObjectsWithoutExpectations]
final class TenantListenerTest extends TestCase
{
    private TenantConnectionSwitcherInterface&MockObject $switcher;
    private TenantRegistryInterface&MockObject $tenantRegistry;
    private TenantContext $tenantContext;
    private TenantListener $listener;

    protected function setUp(): void
    {
        $this->switcher = $this->createMock(TenantConnectionSwitcherInterface::class);
        $this->tenantRegistry = $this->createMock(TenantRegistryInterface::class);
        $this->tenantContext = new TenantContext();

        $this->listener = new TenantListener(
            $this->switcher,
            $this->tenantRegistry,
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

    public function testSkipsAccountApiPath(): void
    {
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('app.example.com', '/api/account/session'));
    }

    public function testResolvesConfiguredTenantForLocalhost(): void
    {
        $this->tenantRegistry
            ->expects($this->once())
            ->method('getTenantByName')
            ->with('demo')
            ->willReturn([
                'id' => 1,
                'name' => 'demo',
                'status' => 'active',
            ]);
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('localhost'));

        $this->assertSame('demo', $this->tenantContext->getTenantName());
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
        $this->tenantRegistry->expects($this->never())->method('getTenantByName');
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('192.168.1.1'));

        $this->assertNull($this->tenantContext->getTenantName());
    }

    public function testResolvesConfiguredTenantForLoopbackIp(): void
    {
        $this->tenantRegistry
            ->expects($this->once())
            ->method('getTenantByName')
            ->with('demo')
            ->willReturn([
                'id' => 1,
                'name' => 'demo',
                'status' => 'active',
            ]);
        $this->switcher->expects($this->never())->method('switchConnection');

        ($this->listener)($this->createEvent('127.0.0.1'));

        $this->assertSame('demo', $this->tenantContext->getTenantName());
    }

    public function testResolvesSubdomainTenant(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];

        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->with('acme')->willReturn($tenantData);

        $this->switcher->expects($this->once())->method('switchConnection')->with('acme');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertSame(1, $this->tenantContext->getTenantId());
        $this->assertSame('acme', $this->tenantContext->getTenantName());
    }

    public function testResolvesByDomainFirst(): void
    {
        $tenantData = ['id' => 2, 'name' => 'corp', 'connection' => 'corp'];

        $this->tenantRegistry->method('getTenantByDomain')->with('custom.corp.com')->willReturn($tenantData);

        $this->tenantRegistry->expects($this->never())->method('getTenantByName');

        $this->switcher->expects($this->once())->method('switchConnection')->with('corp');

        ($this->listener)($this->createEvent('custom.corp.com'));

        $this->assertSame(2, $this->tenantContext->getTenantId());
        $this->assertSame('corp', $this->tenantContext->getTenantName());
    }

    public function testSuspendedTenantIsBlocked(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme', 'status' => 'suspended'];

        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->willReturn($tenantData);

        $this->switcher->expects($this->never())->method('switchConnection');

        $event = $this->createEvent('acme.example.com');
        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Service temporarily unavailable', $body['message']);
    }

    public function testActiveTenantIsAllowed(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme', 'status' => 'active'];

        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertSame('acme', $this->tenantContext->getTenantName());
    }

    public function testErrorDoesNotLeakTenantName(): void
    {
        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->willThrowException(new \RuntimeException('Tenant not found'));

        $event = $this->createEvent('secret-tenant.example.com');
        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('Tenant not found', $body['message']);
    }

    public function testSetsRequestIdFromHeader(): void
    {
        $request = Request::create('https://acme.example.com/api/test');
        $request->headers->set('X-Request-Id', 'custom-req-id');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];
        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($event);

        $this->assertSame('custom-req-id', $this->tenantContext->getRequestId());
    }

    public function testGeneratesRequestIdWhenMissing(): void
    {
        $tenantData = ['id' => 1, 'name' => 'acme', 'connection' => 'acme'];
        $this->tenantRegistry->method('getTenantByDomain')->willReturn(null);
        $this->tenantRegistry->method('getTenantByName')->willReturn($tenantData);
        $this->switcher->method('switchConnection');

        ($this->listener)($this->createEvent('acme.example.com'));

        $this->assertNotNull($this->tenantContext->getRequestId());
        $this->assertNotEmpty($this->tenantContext->getRequestId());
    }
}
