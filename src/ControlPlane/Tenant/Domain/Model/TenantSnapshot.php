<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Domain\Model;

/**
 * Immutable tenant read model exchanged across ControlPlane layers.
 *
 * @phpstan-type TenantArray array{
 *     id: int,
 *     name: string,
 *     connection: string,
 *     status?: string,
 *     plan?: string|null,
 *     backup_frequency?: int|null,
 *     backup_retention_days?: int|null,
 *     primary_domain?: string|null,
 *     deleted_at?: string|null
 * }
 */
final readonly class TenantSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public string $connection,
        public TenantStatus $status = TenantStatus::Active,
        public ?string $plan = null,
        public ?int $backupFrequency = null,
        public ?int $backupRetentionDays = null,
        public ?string $primaryDomain = null,
        public ?string $deletedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            connection: (string) ($row['connection'] ?? ''),
            status: TenantStatus::fromString((string) ($row['status'] ?? 'active')),
            plan: isset($row['plan']) ? (string) $row['plan'] : null,
            backupFrequency: isset($row['backup_frequency']) ? (int) $row['backup_frequency'] : null,
            backupRetentionDays: isset($row['backup_retention_days']) ? (int) $row['backup_retention_days'] : null,
            primaryDomain: isset($row['primary_domain']) ? (string) $row['primary_domain'] : null,
            deletedAt: isset($row['deleted_at']) ? (string) $row['deleted_at'] : null,
        );
    }

    /**
     * @return TenantArray
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'connection' => $this->connection,
            'status' => $this->status->value,
        ];

        if ($this->plan !== null) {
            $data['plan'] = $this->plan;
        }

        if ($this->backupFrequency !== null) {
            $data['backup_frequency'] = $this->backupFrequency;
        }

        if ($this->backupRetentionDays !== null) {
            $data['backup_retention_days'] = $this->backupRetentionDays;
        }

        if ($this->primaryDomain !== null) {
            $data['primary_domain'] = $this->primaryDomain;
        }

        if ($this->deletedAt !== null) {
            $data['deleted_at'] = $this->deletedAt;
        }

        return $data;
    }
}
