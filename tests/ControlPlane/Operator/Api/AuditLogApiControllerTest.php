<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use App\ControlPlane\Operator\Api\AuditLogApiController;
use App\Efact\Audit\AuditLogRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditLogApiControllerTest extends OperatorApiTestCase
{
    public function testIndexReturnsPaginatedAuditLogs(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(true);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 10,
                    'action' => 'tenant.create',
                    'actor' => 'admin',
                    'channel' => 'control_plane',
                    'metadata' => '{}',
                    'created_at' => '2026-07-01 12:00:00',
                    'tenant_name' => 'acme',
                ],
            ]);
        $connection->method('fetchFirstColumn')->willReturn(['tenant.create']);
        $connection->method('createSchemaManager')->willReturn($schema);

        $repository = new AuditLogRepository($this->connectionRegistry($connection), $this->passThruCache());
        $controller = new AuditLogApiController($repository);
        $this->setControllerContainer($controller);

        $request = Request::create('/api/control-plane/audit-log?page=1&action=tenant.create');
        $response = $controller->index($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $payload['total']);
        $this->assertSame('tenant.create', $payload['logs'][0]['action']);
        $this->assertSame(['tenant.create'], $payload['actions']);
    }
}
