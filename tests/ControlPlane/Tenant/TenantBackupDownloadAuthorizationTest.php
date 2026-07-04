<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Tenant;

use App\ControlPlane\Operator\Api\TenantApiController;
use App\ControlPlane\Tenant\Service\TenantBackupService;
use App\ControlPlane\Tenant\Application\TenantService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TenantBackupDownloadAuthorizationTest extends TestCase
{
    public function testDownloadActionRequiresViewerRole(): void
    {
        $method = new \ReflectionMethod(TenantApiController::class, 'downloadBackup');
        $roles = array_map(
            static fn(\ReflectionAttribute $attribute): string => (string) $attribute->getArguments()[0],
            $method->getAttributes(IsGranted::class),
        );

        self::assertContains('ROLE_CP_VIEWER', $roles);
    }

    public function testDownloadActionChecksTenantOwnershipBeforeDownloadingOrOpeningBackup(): void
    {
        $source = (string) file_get_contents(__DIR__
            . '/../../../src/ControlPlane/Operator/Api/TenantApiController.php');
        $methodStart = strpos($source, 'public function downloadBackup');
        self::assertIsInt($methodStart);

        $tenantLookup = strpos($source, '$this->tenantService->getTenant($id)', $methodStart);
        $ownershipCheck = strpos($source, '$this->backupService->findBackupForTenant($backupId, $id)', $methodStart);
        $notFoundGuard = strpos($source, 'throw $this->createNotFoundException(\'Backup not found\')', $methodStart);
        $download = strpos($source, '$this->backupService->downloadBackup($backupId)', $methodStart);
        $fileOpen = strpos($source, 'fopen($localPath, \'r\')', $methodStart);

        self::assertIsInt($tenantLookup);
        self::assertIsInt($ownershipCheck);
        self::assertIsInt($notFoundGuard);
        self::assertIsInt($download);
        self::assertIsInt($fileOpen);
        self::assertLessThan($ownershipCheck, $tenantLookup);
        self::assertLessThan($notFoundGuard, $ownershipCheck);
        self::assertLessThan($download, $notFoundGuard);
        self::assertLessThan($fileOpen, $download);
    }

    public function testFindBackupForTenantReturnsOwnedBackupMetadata(): void
    {
        $backup = [
            'id' => 42,
            'tenant_id' => 7,
            'filename' => 'tenant_20260424.sql.gz',
            'storage_path' => 'tenant/backups/tenant_20260424.sql.gz',
            'size_bytes' => 123,
            'storage_type' => 's3',
            'status' => 'completed',
            'backup_type' => 'full',
            'error_message' => null,
            'created_at' => '2026-04-24 20:00:00',
            'completed_at' => '2026-04-24 20:01:00',
        ];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::stringContains('WHERE id = ? AND tenant_id = ?'),
                [42, 7],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            )
            ->willReturn($backup);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method('readStream');

        self::assertSame($backup, $this->createService($connection, $filesystem)->findBackupForTenant(42, 7));
    }

    public function testFindBackupForTenantReturnsNullForWrongTenantWithoutReadingStorage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::stringContains('WHERE id = ? AND tenant_id = ?'),
                [42, 99],
                [ParameterType::INTEGER, ParameterType::INTEGER],
            )
            ->willReturn(false);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method('readStream');

        self::assertNull($this->createService($connection, $filesystem)->findBackupForTenant(42, 99));
    }

    private function createService(Connection $connection, FilesystemOperator $filesystem): TenantBackupService
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->with('control_plane')->willReturn($connection);

        return new TenantBackupService(
            $this->createStub(TenantService::class),
            $registry,
            $filesystem,
            new NullLogger(),
            '',
        );
    }
}