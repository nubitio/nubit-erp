<?php

declare(strict_types=1);

namespace App\Core\Audit;

use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
final readonly class AuditLogMessageHandler
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AuditLogMessage $message): void
    {
        if (!$this->isControlPlaneReady()) {
            $this->logger->warning('Control plane not ready, dropping audit log', [
                'action' => $message->action,
            ]);

            return;
        }

        try {
            $this->connectionRegistry->getConnection('control_plane')->insert('audit_logs', [
                'tenant_id' => $message->tenantId,
                'action' => $message->action,
                'actor' => $message->actor,
                'request_id' => $message->requestId,
                'channel' => $message->channel,
                'metadata' => $message->metadata,
                'created_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to persist audit log', [
                'action' => $message->action,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function isControlPlaneReady(): bool
    {
        return $this->cache->get('audit_control_plane_ready', function (ItemInterface $item): bool {
            $item->expiresAfter(600);

            return $this->connectionRegistry->getConnection('control_plane')
                ->createSchemaManager()
                ->tablesExist(['audit_logs']);
        });
    }
}
