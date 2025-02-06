<?php

namespace App\Warehouse\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Entity\Branch;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(string $id): ?Warehouse
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Warehouse::class);
    }

    public function insert(Warehouse $warehouse): Warehouse
    {
        $entity = DataMapper::map($warehouse, WarehouseEntity::class);
        $branch = $this->entityManager->getReference(Branch::class, $warehouse->local?->local_id);
        $entity->local = $branch;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Warehouse::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Warehouse $warehouse): Warehouse
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $warehouse->almacen_id);

        if (!$entity) {
            throw new ServiceException('No se encontró el almacén');
        }

        $entity = DataMapper::map($warehouse, $entity);
        $branch = $this->entityManager->getReference(Branch::class, $warehouse->local?->local_id);
        $entity->local = $branch;
        $this->entityManager->flush();

        return DataMapper::map($entity, Warehouse::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(WarehouseEntity::class, $id);

        if (!$entity) {
            return;
        }

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
        /** @var WarehouseEntity[] $result */
        $result = $query->getResult();
        $data = [];
        foreach ($result as $warehouse) {
            $data[] = DataMapper::map($warehouse, Warehouse::class);
        }

        return $data;
    }
}
