<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use App\ControlPlane\Dashboard\DashboardService;
use App\ControlPlane\Operator\Api\DashboardApiController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

final class DashboardApiControllerTest extends OperatorApiTestCase
{
    public function testInvokeReturnsDashboardPayload(): void
    {
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema->method('tablesExist')->willReturn(true);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(3, 2, 1, 0, 2, 0, 5, '2026-07-01 10:00:00', 1024, 1);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['action' => 'tenant.create', 'count' => 2],
            ]);
        $connection->method('createSchemaManager')->willReturn($schema);

        $service = new DashboardService($this->connectionRegistry($connection), new NullLogger());
        $controller = new DashboardApiController($service);
        $this->setControllerContainer($controller);

        $response = $controller();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame(3, $payload['tenantStats']['total']);
        $this->assertSame(2, $payload['planStats']['total_plans']);
        $this->assertSame(5, $payload['backupStats']['total_backups']);
        $this->assertSame('tenant.create', $payload['auditSummary'][0]['action']);
    }
}
