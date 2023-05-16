<?php

namespace App\Supplier\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Supplier\Domain\Supplier;
use App\Supplier\Domain\SupplierRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class SupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Supplier
    {
        /** @var ?SupplierEntity $entity */
        $entity = $this->entityManager->find(SupplierEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Supplier::class);
    }

    public function insert(Supplier $supplier): Supplier
    {
        $entity = DataMapper::map($supplier, SupplierEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Supplier::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(Supplier $supplier): Supplier
    {
        $entity = $this->entityManager->find(SupplierEntity::class, $supplier->proveedor_id);

        if (!$entity) {
            throw new ServiceException('No se encontró el proveedor');
        }

        $entity = DataMapper::map($supplier, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Supplier::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SupplierEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
