<?php

declare(strict_types=1);

namespace App\AI\Service;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Nubit\AdminBundle\Auth\JWTManager;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Throwable;

/**
 * Builds a dynamic session-context block to prepend to every AI system prompt.
 *
 * The context block injects:
 *   - user_id / user_name / role
 *   - branch_id / branch_name
 *   - cash_register_id (active, if any) / cash_session_status
 *   - tenant_name
 *   - current_datetime (America/Lima)
 */
final readonly class SessionContextBuilder
{
    public function __construct(
        private Security $security,
        private JWTManager $jwtManager,
        private TenantContext $tenantContext,
        private Connection $connection,
    ) {}

    public function build(): string
    {
        $payload = $this->decodeCurrentToken();

        $userId = (string) ($payload['userId'] ?? 'desconocido');
        $userName = (string) ($payload['username'] ?? 'desconocido');
        $role = $this->resolveRoleLabel((int) ($payload['role'] ?? 0));
        $rawBranchId = $payload['branchId'] ?? null;
        $branchId = in_array($rawBranchId, [null, false, ''], true) ? null : (string) $rawBranchId;
        $rawBranchName = $payload['branchName'] ?? null;
        $branchName = in_array($rawBranchName, [null, false, ''], true) ? null : (string) $rawBranchName;

        [$cashRegisterId, $cashSessionStatus] = $this->resolveCashSession($branchId);

        $tenantName = $this->tenantContext->getTenantName() ?? 'desconocido';
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->format(DateTimeInterface::ATOM);

        return implode("\n", [
            '## Contexto de sesión actual',
            sprintf('- Usuario: %s (ID: %s)', $userName, $userId),
            sprintf('- Rol: %s', $role),
            sprintf('- Sucursal: %s (ID: %s)', $branchName ?? 'ninguna', $branchId ?? 'ninguna'),
            sprintf('- Caja: %s — Estado: %s', $cashRegisterId ?? 'ninguna', $cashSessionStatus),
            sprintf('- Tenant: %s', $tenantName),
            sprintf('- Fecha y hora actual (Lima): %s', $now),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeCurrentToken(): array
    {
        $token = $this->security->getToken();
        if (!$token instanceof JWTAuthenticationToken) {
            return [];
        }

        try {
            return $this->jwtManager->decode($token->getCredentials());
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveRoleLabel(int $roleId): string
    {
        return match ($roleId) {
            1 => 'SUPER_ADMIN',
            2 => 'ADMIN',
            3 => 'CASHIER',
            default => 'UNKNOWN',
        };
    }

    /**
     * @return array{string|null, string}  [cash_register_id, status]
     */
    private function resolveCashSession(?string $branchId): array
    {
        if ($branchId === null) {
            return [null, 'NONE'];
        }

        try {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT cash_register_id, status
                    FROM cash_sessions
                    WHERE branch_id = :branch_id
                    ORDER BY
                        CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
                        opened_at DESC
                    LIMIT 1
                    SQL,
                ['branch_id' => $branchId],
            );
        } catch (Throwable) {
            return [null, 'NONE'];
        }

        if ($row === false) {
            return [null, 'NONE'];
        }

        $cashRegisterId = $row['cash_register_id'] !== null ? (string) $row['cash_register_id'] : null;
        $status = (string) ($row['status'] ?? 'CLOSED');

        return [$cashRegisterId, $status];
    }
}
