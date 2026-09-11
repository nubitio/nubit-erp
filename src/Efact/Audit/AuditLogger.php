<?php

declare(strict_types=1);

namespace App\Efact\Audit;

use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

readonly class AuditLogger
{
    public function __construct(
        private TenantContext $tenantContext,
        private Security $security,
        private TenantRegistryInterface $tenantRegistry,
        private LoggerInterface $logger,
        private MessageBusInterface $bus,
        private AuditLogMessageHandler $handler,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(string $action, array $metadata = [], ?int $tenantId = null, ?string $actor = null): void
    {
        $this->bus->dispatch($this->message($action, $metadata, $tenantId, $actor));
    }

    /** Persist immediately, for use inside the mutation transaction. */
    public function logDurably(string $action, array $metadata = [], ?int $tenantId = null, ?string $actor = null): void
    {
        $this->handler->persist($this->message($action, $metadata, $tenantId, $actor));
    }

    /** @param array<string, mixed> $metadata */
    private function message(string $action, array $metadata, ?int $tenantId, ?string $actor): AuditLogMessage
    {
        $actor = $this->resolveActor($actor, $metadata);
        $tenantId = $tenantId ?? $this->tenantContext->getTenantId() ?? $this->resolveTenantId($metadata);

        return new AuditLogMessage(
            action: $action,
            metadata: json_encode($metadata, JSON_THROW_ON_ERROR),
            tenantId: $tenantId,
            actor: $actor,
            channel: $this->tenantContext->getChannel(),
            requestId: $this->tenantContext->getRequestId(),
        );
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
            $tenant = $this->tenantRegistry->getTenantByName($tenantName);
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
}
