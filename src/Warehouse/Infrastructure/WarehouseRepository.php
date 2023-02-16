<?php

namespace App\Warehouse\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Warehouse
    {
        /** @var ?WarehouseEntity $entity */
        $entity = $this->entityManager->find(WarehouseEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Warehouse::class);
    }

    public function insert(Warehouse $warehouse): Warehouse
    {
        $entity = DataMapper::map($warehouse, WarehouseEntity::class);
        $branch = $this->entityManager->getReference(BranchEntity::class, $warehouse->local->local_id);
        $entity->local = $branch;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Warehouse::class);
    }

    public function update(Warehouse $warehouse): Warehouse
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $warehouse->almacen_id);
        $entity = DataMapper::map($warehouse, $entity);
        $branch = $this->entityManager->getReference(BranchEntity::class, $warehouse->local->local_id);
        $entity->local = $branch;
        $this->entityManager->flush();

        return DataMapper::map($entity, Warehouse::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /** @return Warehouse[] */
    public function getAllByBranch(string $branchId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('w')
            ->from(WarehouseEntity::class, 'w')
            ->where('w.local = :branchId')
            ->setParameter('branchId', $branchId);
        $query = $qb->getQuery();
        $result = $query->getResult();
        $data = [];
        foreach ($result as $warehouse) {
            $data[] = DataMapper::map($warehouse, Warehouse::class);
        }
        return $data;
    }
}