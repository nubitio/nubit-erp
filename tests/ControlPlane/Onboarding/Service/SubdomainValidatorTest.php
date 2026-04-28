<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Onboarding\Service;

use App\ControlPlane\Onboarding\Service\SubdomainValidator;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubdomainValidatorTest extends TestCase
{
    private ConnectionRegistry&MockObject $registry;
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ConnectionRegistry::class);
        $this->connection = $this->createMock(Connection::class);

        $this->registry->method('getConnection')
            ->with('control_plane')
            ->willReturn($this->connection);

        $_ENV['ONBOARDING_DOMAIN'] = 'app.efact.app';
    }

    public function testRejectsInvalidSubdomainFormat(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $validator = new SubdomainValidator($this->registry);
        $result = $validator->check('@@@');

        $this->assertFalse($result['available']);
        $this->assertSame('Formato invalido (3-63 chars, a-z, 0-9 y guion).', $result['reason']);
    }

    public function testRejectsReservedSubdomain(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $validator = new SubdomainValidator($this->registry);
        $result = $validator->check('admin');

        $this->assertFalse($result['available']);
        $this->assertSame('Subdominio reservado.', $result['reason']);
    }

    public function testReturnsAvailableWhenNoConflictExists(): void
    {
        $this->connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturn(false);

        $validator = new SubdomainValidator($this->registry);
        $result = $validator->check('acme');

        $this->assertTrue($result['available']);
        $this->assertSame('acme', $result['normalized']);
        $this->assertSame('acme.app.efact.app', $result['domain']);
    }

    public function testSlugifiesTenantName(): void
    {
        $validator = new SubdomainValidator($this->registry);

        $this->assertSame('mi-empresa-sac', $validator->slugifyTenantName('Mi Empresa SAC'));
    }
}
