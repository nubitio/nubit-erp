<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Repository;

use App\ControlPlane\Billing\Entity\PlanFeature;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;

/**
 * Reads and writes `plan_features` rows from the control-plane database.
 *
 * The control-plane connection has no dedicated ORM entity manager
 * (see config/packages/doctrine.yaml), so raw DBAL is used — consistent
 * with all other ControlPlane repositories in this project.
 */
final readonly class PlanFeatureRepository implements PlanFeatureRepositoryInterface
{
    private Connection $cp;

    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    /**
     * Returns all features configured for the given plan.
     *
     * @return list<PlanFeature>
     */
    public function findByPlan(string $planName): array
    {
        $rows = $this->cp->fetchAllAssociative(
            'SELECT * FROM plan_features WHERE plan_name = ? ORDER BY feature_key ASC',
            [$planName],
            [ParameterType::STRING],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Returns a single feature entry for the given plan + key combination, or null.
     */
    public function findOneByPlanAndKey(string $planName, string $featureKey): ?PlanFeature
    {
        $row = $this->cp->fetchAssociative(
            'SELECT * FROM plan_features WHERE plan_name = ? AND feature_key = ? LIMIT 1',
            [$planName, $featureKey],
            [ParameterType::STRING, ParameterType::STRING],
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Creates or updates a plan_features row.
     * Safe to call for both new and existing rows — the UNIQUE KEY on
     * (plan_name, feature_key) handles deduplication via ON DUPLICATE KEY UPDATE.
     *
     * @param array<string, mixed>|null $config
     */
    public function upsert(string $planName, string $featureKey, bool $isEnabled, ?array $config): void
    {
        $now        = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $configJson = $config !== null ? json_encode($config, JSON_THROW_ON_ERROR) : null;

        $this->cp->executeStatement(
            'INSERT INTO plan_features
                 (plan_name, feature_key, is_enabled, config_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 is_enabled  = VALUES(is_enabled),
                 config_json = VALUES(config_json),
                 updated_at  = VALUES(updated_at)',
            [$planName, $featureKey, $isEnabled ? 1 : 0, $configJson, $now, $now],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );
    }

    // -------------------------------------------------------------------------
    // Hydration
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PlanFeature
    {
        /** @var array<string, mixed>|null $configJson */
        $configJson = isset($row['config_json'])
            ? json_decode((string) $row['config_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new PlanFeature(
            id: (int) $row['id'],
            planName: (string) $row['plan_name'],
            featureKey: (string) $row['feature_key'],
            enabled: (bool) $row['is_enabled'],
            configJson: $configJson,
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            updatedAt: new \DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
