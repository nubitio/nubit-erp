<?php

declare(strict_types=1);

namespace App\Inventory\Adapter;

use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Repository\BranchRepository;
use App\Settings\Contract\TenantWarehousePortInterface;
use App\Settings\Dto\TenantWarehouseView;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TenantWarehouseSettingsAdapter implements TenantWarehousePortInterface
{
    public function __construct(
        private WarehouseRepository $warehouses,
        private BranchRepository $branches,
        private EntityManagerInterface $entityManager,
    ) {}

    public function count(): int
    {
        return $this->warehouses->count([]);
    }

    public function defaultWarehouse(): ?TenantWarehouseView
    {
        $warehouse = $this->warehouses->findOneBy([], ['name' => 'ASC']);

        return $warehouse === null ? null : new TenantWarehouseView($warehouse->getId(), $warehouse->getName());
    }

    public function hasBranch(): bool
    {
        return $this->branches->findOneBy([], ['name' => 'ASC']) !== null;
    }

    public function createDefault(string $name): void
    {
        $branch = $this->branches->findOneBy([], ['name' => 'ASC']);
        if ($branch === null) {
            return;
        }

        $this->entityManager->persist(
            new Warehouse()
                ->setName($name)
                ->setBranch($branch),
        );
    }
}
