<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

final readonly class TenantCertificateRepository implements TenantCertificateRepositoryInterface
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
    ) {}

    /**
     * @return list<TenantCertificate>
     */
    public function listByTenant(int $tenantId): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT * FROM tenant_certificates WHERE tenant_id = ? ORDER BY uploaded_at DESC, id DESC',
            [$tenantId],
            [ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function findActiveByTenantAndRuc(int $tenantId, string $ruc): ?TenantCertificate
    {
        $row = $this->connection()->fetchAssociative(
            "SELECT * FROM tenant_certificates WHERE tenant_id = ? AND ruc = ? AND status IN ('active', 'expiring_soon', 'expired') ORDER BY uploaded_at DESC, id DESC LIMIT 1",
            [$tenantId, $ruc],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByTenantAndId(int $tenantId, int $id): ?TenantCertificate
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM tenant_certificates WHERE tenant_id = ? AND id = ? LIMIT 1',
            [$tenantId, $id],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByTenantAndFingerprint(int $tenantId, string $fingerprintSha256): ?TenantCertificate
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM tenant_certificates WHERE tenant_id = ? AND fingerprint_sha256 = ? LIMIT 1',
            [$tenantId, $fingerprintSha256],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<TenantCertificate>
     */
    public function findExpiringWithin(int $days): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            "SELECT * FROM tenant_certificates
             WHERE status IN ('active', 'expiring_soon')
               AND expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
             ORDER BY expires_at ASC",
            [$days],
            [ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function insert(
        int $tenantId,
        string $tenantName,
        string $ruc,
        CertificateStatus $status,
        CertificateMetadata $metadata,
        ?string $uploadedBy,
    ): int {
        $this->connection()->insert('tenant_certificates', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'ruc' => $ruc,
            'status' => $status->value,
            'subject_cn' => $metadata->subjectCn,
            'subject_json' => json_encode($metadata->subject, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'issuer_cn' => $metadata->issuerCn,
            'issuer_json' => json_encode($metadata->issuer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'serial_number' => $metadata->serialNumber,
            'fingerprint_sha256' => $metadata->fingerprintSha256,
            'valid_from' => $metadata->validFrom->format('Y-m-d H:i:s'),
            'expires_at' => $metadata->expiresAt->format('Y-m-d H:i:s'),
            'uploaded_by' => $uploadedBy,
            'lycet_sync_status' => 'upsert_pending',
            'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @return list<TenantCertificate>
     */
    public function findStuckLycetSyncs(int $minutesThreshold): array
    {
        $rows = $this->connection()->fetchAllAssociative(
            "SELECT * FROM tenant_certificates
             WHERE lycet_sync_status NOT IN ('synced')
               AND lycet_sync_updated_at IS NOT NULL
               AND lycet_sync_updated_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
             ORDER BY lycet_sync_updated_at ASC",
            [$minutesThreshold],
            [ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function markSuperseded(int $id, int $supersededById): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'status' => CertificateStatus::Superseded->value,
                'superseded_by_id' => $supersededById,
            ],
            ['id' => $id],
        );
    }

    public function markOtherActiveCertificatesSuperseded(int $tenantId, string $ruc, int $supersededById): void
    {
        $this->connection()->executeStatement(
            "UPDATE tenant_certificates
             SET status = ?, superseded_by_id = ?
             WHERE tenant_id = ? AND ruc = ? AND id <> ? AND status IN ('active', 'expiring_soon', 'expired')",
            [CertificateStatus::Superseded->value, $supersededById, $tenantId, $ruc, $supersededById],
            [
                ParameterType::STRING,
                ParameterType::INTEGER,
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::INTEGER,
            ],
        );
    }

    public function markLycetSyncPending(int $id, string $status): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'lycet_sync_status' => $status,
                'lycet_sync_error' => null,
                'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public function markLycetSyncFailed(int $id, string $status, string $error): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'lycet_sync_status' => $status,
                'lycet_sync_error' => mb_substr($error, 0, 1000),
                'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public function markLycetSynced(int $id): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'lycet_sync_status' => 'synced',
                'lycet_sync_error' => null,
                'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public function markRevocationPending(int $id, string $revokedBy, string $reason): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'revoked_by' => $revokedBy,
                'revocation_reason' => $reason,
                'lycet_sync_status' => 'revocation_pending',
                'lycet_sync_error' => null,
                'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public function revoke(int $id, string $revokedBy, string $reason): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'status' => CertificateStatus::Revoked->value,
                'revoked_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
                'revoked_by' => $revokedBy,
                'revocation_reason' => $reason,
                'lycet_sync_status' => 'synced',
                'lycet_sync_error' => null,
                'lycet_sync_updated_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    public function refreshStatus(int $id, CertificateStatus $status): void
    {
        $this->connection()->update('tenant_certificates', ['status' => $status->value], ['id' => $id]);
    }

    public function markAlerted(int $id): void
    {
        $this->connection()->update(
            'tenant_certificates',
            [
                'last_alerted_at' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
            ['id' => $id],
        );
    }

    private function connection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TenantCertificate
    {
        return new TenantCertificate(
            id: (int) $row['id'],
            tenantId: (int) $row['tenant_id'],
            tenantName: (string) $row['tenant_name'],
            ruc: (string) $row['ruc'],
            status: CertificateStatus::from((string) $row['status']),
            subjectCn: isset($row['subject_cn']) ? (string) $row['subject_cn'] : null,
            issuerCn: isset($row['issuer_cn']) ? (string) $row['issuer_cn'] : null,
            serialNumber: isset($row['serial_number']) ? (string) $row['serial_number'] : null,
            fingerprintSha256: (string) $row['fingerprint_sha256'],
            validFrom: new \DateTimeImmutable((string) $row['valid_from']),
            expiresAt: new \DateTimeImmutable((string) $row['expires_at']),
            uploadedAt: new \DateTimeImmutable((string) $row['uploaded_at']),
            uploadedBy: isset($row['uploaded_by']) ? (string) $row['uploaded_by'] : null,
            revokedAt: isset($row['revoked_at']) ? new \DateTimeImmutable((string) $row['revoked_at']) : null,
            revokedBy: isset($row['revoked_by']) ? (string) $row['revoked_by'] : null,
            revocationReason: isset($row['revocation_reason']) ? (string) $row['revocation_reason'] : null,
            supersededById: isset($row['superseded_by_id']) ? (int) $row['superseded_by_id'] : null,
            lastAlertedAt: isset($row['last_alerted_at'])
                ? new \DateTimeImmutable((string) $row['last_alerted_at'])
                : null,
            lycetSyncStatus: isset($row['lycet_sync_status']) ? (string) $row['lycet_sync_status'] : 'synced',
            lycetSyncError: isset($row['lycet_sync_error']) ? (string) $row['lycet_sync_error'] : null,
            lycetSyncUpdatedAt: isset($row['lycet_sync_updated_at'])
                ? new \DateTimeImmutable((string) $row['lycet_sync_updated_at'])
                : null,
        );
    }
}
