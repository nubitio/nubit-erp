<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Core\Context\TenantContext;
use App\Core\Service\TenantManager;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

final readonly class AuditLogger
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private TenantContext $tenantContext,
        private Security $security,
        private TenantManager $tenantManager,
        private LoggerInterface $logger,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        array $metadata = [],
        ?int $tenantId = null,
        ?string $actor = null,
    ): void {
        $connection = $this->getControlPlaneConnection();
        if (!$this->isControlPlaneReady()) {
            throw new RuntimeException('Control plane database not ready for audit logging.');
        }

        $actor = $this->resolveActor($actor, $metadata);
        $tenantId = $tenantId ?? $this->tenantContext->getTenantId() ?? $this->resolveTenantId($metadata);
        $requestId = $this->tenantContext->getRequestId();
        $channel = $this->tenantContext->getChannel();

        $connection->insert('audit_logs', [
            'tenant_id' => $tenantId,
            'action' => $action,
            'actor' => $actor,
            'request_id' => $requestId,
            'channel' => $channel,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => new \DateTimeImmutable('now')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveActor(?string $actor, array $metadata): string
    {
        $actor = trim((string) $actor);
        if ($actor !== '') {
            return $actor;
        }

        $contextActor = $this->tenantContext->getActorIdentifier();
        if ($contextActor !== null && trim($contextActor) !== '') {
            return trim($contextActor);
        }

        $user = $this->security->getUser();
        if ($user !== null) {
            $identifier = trim((string) $user->getUserIdentifier());
            if ($identifier !== '') {
                return $identifier;
            }
        }

        foreach (['actor', 'username', 'admin_user', 'user'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key])) {
                $candidate = trim($metadata[$key]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return 'system';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveTenantId(array $metadata): ?int
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null || trim($tenantName) === '') {
            $tenantName = $this->extractTenantNameFromMetadata($metadata);
        }

        if ($tenantName === null || trim($tenantName) === '') {
            return null;
        }

        try {
            $tenant = $this->tenantManager->getTenantByName($tenantName);
            if ($tenant === null) {
                return null;
            }

            $tenantId = isset($tenant['id']) ? (int) $tenant['id'] : null;

            return $tenantId === 0 ? null : $tenantId;
        } catch (Throwable $e) {
            $this->logger->debug('Audit tenant resolution skipped', [
                'tenant' => $tenantName,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extractTenantNameFromMetadata(array $metadata): ?string
    {
        foreach (['tenant', 'tenant_name'] as $key) {
            if (!isset($metadata[$key]) || !is_string($metadata[$key])) {
                continue;
            }

            $tenantName = trim($metadata[$key]);
            if ($tenantName !== '') {
                return $tenantName;
            }
        }

        return null;
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    private function isControlPlaneReady(): bool
    {
        return $this->cache->get('audit_control_plane_ready', function (ItemInterface $item): bool {
            $item->expiresAfter(600);

            return $this->getControlPlaneConnection()
                ->createSchemaManager()
                ->tablesExist(['audit_logs']);
        });
    }
}
