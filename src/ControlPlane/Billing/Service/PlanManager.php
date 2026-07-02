<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Service;

use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Tenant\Service\TenantManager;
use App\ControlPlane\Tenant\Service\TenantResourceCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class PlanManager
{
    private const int CACHE_TTL = 600;
    private const string CACHE_KEY_ALL = 'plans_all';

    public function __construct(
        private ConnectionRegistry $connectionRegistry,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private TenantResourceCatalog $resourceCatalog,
        private ?PlanFeatureManager $planFeatureManager = null,
    ) {}

    /**
     * @return array<int, array{id: int, name: string, limits: array<string, int>, is_default: bool}>
     */
    public function getPlans(): array
    {
        return $this->cache->get(self::CACHE_KEY_ALL, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->loadPlansFromDatabase();
        });
    }

    /**
     * @return array<string, int>
     */
    public function getLimitsForPlan(string $planName): array
    {
        $plans = $this->getPlans();
        if ($plans === []) {
            return $this->closedLimits();
        }

        foreach ($plans as $plan) {
            if ($plan['name'] === $planName) {
                return $plan['limits'];
            }
        }

        $default = $this->getDefaultPlan();
        if ($default === null) {
            return $this->closedLimits();
        }

        return $default['limits'];
    }

    /**
     * @return array{id: int, name: string, limits: array<string, int>, is_default: bool}|null
     */
    public function getPlan(int $id): ?array
    {
        $conn = $this->getConnection();

        /** @var array{id: int, name: string, limits_json: string, is_default: int}|false $row */
        $row = $conn->fetchAssociative('SELECT id, name, limits_json, is_default FROM plans WHERE id = ?', [$id]);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array{id: int, name: string, limits: array<string, int>, is_default: bool}|null
     */
    public function getPlanByName(string $name): ?array
    {
        $conn = $this->getConnection();

        /** @var array{id: int, name: string, limits_json: string, is_default: int}|false $row */
        $row = $conn->fetchAssociative('SELECT id, name, limits_json, is_default FROM plans WHERE name = ?', [$name]);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array{id: int, name: string, limits: array<string, int>, is_default: bool}|null
     */
    public function getDefaultPlan(): ?array
    {
        $plans = $this->getPlans();
        foreach ($plans as $plan) {
            if ($plan['is_default']) {
                return $plan;
            }
        }

        return $plans[0] ?? null;
    }

    /**
     * @param array<string, int> $limits
     */
    public function createPlan(string $name, array $limits, bool $isDefault = false): void
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            throw new InvalidArgumentException('Plan name is required');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException(
                'Plan name must contain only lowercase letters, numbers, hyphens and underscores',
            );
        }
        $limits = $this->normalizeLimits($limits);

        $conn = $this->getConnection();

        $existing = $conn->fetchOne('SELECT id FROM plans WHERE name = ?', [$name]);
        if ($existing !== false) {
            throw new InvalidArgumentException(sprintf('Plan "%s" already exists', $name));
        }

        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');

        if ($isDefault) {
            $conn->executeStatement('UPDATE plans SET is_default = 0');
        }

        $conn->insert('plans', [
            'name' => $name,
            'limits_json' => json_encode($limits, JSON_THROW_ON_ERROR),
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Seed all feature keys as disabled so the plan feature matrix is pre-populated.
        $this->planFeatureManager?->seedFeaturesForNewPlan($name);

        $this->invalidateCache();
    }

    /**
     * @param array<string, int> $limits
     */
    public function updatePlan(int $id, string $name, array $limits, bool $isDefault = false): void
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            throw new InvalidArgumentException('Plan name is required');
        }
        $limits = $this->normalizeLimits($limits);

        $conn = $this->getConnection();

        $oldPlan = $this->getPlan($id);
        if ($oldPlan === null) {
            throw new InvalidArgumentException('Plan not found');
        }

        $existing = $conn->fetchOne('SELECT id FROM plans WHERE name = ? AND id <> ?', [$name, $id]);
        if ($existing !== false) {
            throw new InvalidArgumentException(sprintf('Plan "%s" already exists', $name));
        }

        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');

        if ($isDefault) {
            $conn->executeStatement('UPDATE plans SET is_default = 0');
        }

        $conn->update(
            'plans',
            [
                'name' => $name,
                'limits_json' => json_encode($limits, JSON_THROW_ON_ERROR),
                'is_default' => $isDefault ? 1 : 0,
                'updated_at' => $now,
            ],
            ['id' => $id],
        );

        if ($oldPlan['name'] !== $name) {
            $conn->update('tenants', ['plan' => $name], ['plan' => $oldPlan['name']]);
        }

        $this->invalidateCache();
    }

    /**
     * @throws DbalException
     */
    public function deletePlan(int $id): void
    {
        $conn = $this->getConnection();

        $plan = $this->getPlan($id);
        if ($plan === null) {
            throw new InvalidArgumentException('Plan not found');
        }

        if ($plan['is_default']) {
            throw new InvalidArgumentException('Cannot delete the default plan');
        }

        $tenantsUsing = (int) $conn->fetchOne('SELECT COUNT(*) FROM tenants WHERE plan = ?', [$plan['name']]);
        if ($tenantsUsing > 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot delete plan "%s": %d tenant(s) are using it',
                $plan['name'],
                $tenantsUsing,
            ));
        }

        $conn->delete('plans', ['id' => $id]);
        $this->invalidateCache();
    }

    public function planExists(string $name): bool
    {
        $plans = $this->getPlans();
        foreach ($plans as $plan) {
            if ($plan['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getPlansAsLimitsMap(): array
    {
        $map = [];
        foreach ($this->getPlans() as $plan) {
            $map[$plan['name']] = $plan['limits'];
        }

        return $map;
    }

    /**
     * @return array<int, array{id: int, name: string, limits: array<string, int>, is_default: bool}>
     */
    private function loadPlansFromDatabase(): array
    {
        try {
            $conn = $this->getConnection();

            /** @var array<int, array{id: int, name: string, limits_json: string, is_default: int}> $rows */
            $rows = $conn->fetchAllAssociative('SELECT id, name, limits_json, is_default FROM plans ORDER BY id ASC');

            return array_map($this->hydrate(...), $rows);
        } catch (DbalException $e) {
            $this->logger->warning('Failed to load plans from database', ['exception' => $e]);

            return [];
        }
    }

    /**
     * @param array{id: int, name: string, limits_json: string, is_default: int} $row
     * @return array{id: int, name: string, limits: array<string, int>, is_default: bool}
     */
    private function hydrate(array $row): array
    {
        /** @var array<string, int> $limits */
        $limits = json_decode((string) $row['limits_json'], true, 512, JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'limits' => $limits,
            'is_default' => (bool) $row['is_default'],
        ];
    }

    private function getConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }

    private function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY_ALL);
    }

    /**
     * @return array<string, int>
     */
    private function closedLimits(): array
    {
        $limits = [];
        foreach ($this->resourceCatalog->allowedPlanLimitKeys() as $key) {
            $limits[$key] = 0;
        }

        return $limits;
    }

    /**
     * @param array<string, int> $limits
     *
     * @return array<string, int>
     */
    private function normalizeLimits(array $limits): array
    {
        $allowedKeys = $this->resourceCatalog->allowedPlanLimitKeys();
        $normalized = [];

        foreach ($limits as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException(sprintf('Invalid limit key "%s"', $key));
            }
            if ($value < -1) {
                throw new InvalidArgumentException(sprintf('Limit "%s" must be -1 or greater', $key));
            }

            $normalized[$key] = $value;
        }

        foreach ($allowedKeys as $key) {
            if (!isset($normalized[$key])) {
                $normalized[$key] = -1;
            }
        }

        return $normalized;
    }
}
