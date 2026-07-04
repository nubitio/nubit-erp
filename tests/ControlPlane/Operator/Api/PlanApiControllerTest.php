<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use App\ControlPlane\Billing\Service\PlanManager;
use App\ControlPlane\Operator\Api\PlanApiController;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use App\Efact\Audit\AuditLogger;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AllowMockObjectsWithoutExpectations]
final class PlanApiControllerTest extends TestCase
{
    private AuditLogger&MockObject $auditLogger;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
    }

    public function testIndexReturnsPlans(): void
    {
        $response = $this->controller()->index();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('plans', $payload);
        $this->assertCount(1, $payload['plans']);
        $this->assertSame('free', $payload['plans'][0]['name']);
    }

    public function testCreatePersistsPlan(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchOne')->willReturn(false);
        $conn->expects($this->once())->method('insert');

        $this->auditLogger
            ->expects($this->once())
            ->method('log')
            ->with('plan.create', $this->isType('array'));

        $request = Request::create(
            '/api/control-plane/plans',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'pro',
                'limits' => [
                    'users' => 10,
                    'customers' => 100,
                    'products' => 50,
                    'invoices' => 500,
                    'branches' => 1,
                    'storage_mb' => 200,
                ],
                'isDefault' => false,
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->controller($conn)->create($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    private function controller(?Connection $connection = null): PlanApiController
    {
        $controller = new PlanApiController($this->planManager($connection), $this->auditLogger);
        $controller->setContainer(new \Symfony\Component\DependencyInjection\Container());

        return $controller;
    }

    private function planManager(?Connection $connection = null): PlanManager
    {
        $conn = $connection ?? $this->defaultConnection();

        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($conn);

        return new PlanManager($registry, $this->passThruCache(), new NullLogger(), new TenantResourceCatalog());
    }

    private function defaultConnection(): Connection
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            [
                'id' => 1,
                'name' => 'free',
                'limits_json' => '{"users":-1,"customers":-1,"products":-1,"invoices":-1,"branches":-1,"storage_mb":-1}',
                'is_default' => 1,
            ],
        ]);

        return $conn;
    }

    private function passThruCache(): CacheInterface
    {
        $item = $this->createStub(ItemInterface::class);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static fn(string $key, callable $cb): mixed => $cb($item));

        return $cache;
    }
}