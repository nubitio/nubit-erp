<?php

declare(strict_types=1);

namespace App\Efact\Audit;

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
    ) {}

    public function __invoke(AuditLogMessage $message): void
    {
        $this->persist($message);
    }

    public function persist(AuditLogMessage $message): void
    {
        if (!$this->isControlPlaneReady()) {
            $this->logger->warning('Control plane not ready, audit log will be retried', [
                'action' => $message->action,
            ]);

            throw new \RuntimeException('Control plane audit storage is not ready.');
        }

        try {
            $this->connectionRegistry->getConnection('control_plane')->insert('audit_logs', [
                'tenant_id' => $message->tenantId,
                'action' => $message->action,
                'actor' => $message->actor,
                'request_id' => $message->requestId,
                'channel' => $message->channel,
                'metadata' => $message->metadata,
                'created_at' => new \DateTimeImmutable('now')->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to persist audit log', [
                'action' => $message->action,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function isControlPlaneReady(): bool
    {
        return $this->cache->get('audit_control_plane_ready', function (ItemInterface $item): bool {
            $item->expiresAfter(5);

            return $this->connectionRegistry
                ->getConnection('control_plane')
                ->createSchemaManager()
                ->tablesExist(['audit_logs']) === true;
        }) === true;
    }
}
