<?php

declare(strict_types=1);

namespace App\Core\MessageHandler;

use App\Core\Audit\AuditLogger;
use App\Core\Message\TenantBackupMessage;
use App\ControlPlane\Tenant\Service\TenantBackupService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TenantBackupMessageHandler
{
    public function __construct(
        private TenantBackupService $backupService,
        private AuditLogger $auditLogger,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TenantBackupMessage $message): void
    {
        try {
            $result = $this->backupService->backup($message->tenantName, true);
            $this->auditLogger->log('tenant.backup.create', [
                'tenant_id' => $message->tenantId,
                'backup_id' => $result['id'],
                'filename' => $result['filename'],
                'source' => 'async',
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Async tenant backup failed', [
                'tenant_id' => $message->tenantId,
                'tenant_name' => $message->tenantName,
                'exception' => $exception,
            ]);
        }
    }
}
