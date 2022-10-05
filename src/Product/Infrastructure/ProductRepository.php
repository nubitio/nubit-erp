<?php

namespace App\Product\Infrastructure;

use App\Product\Domain\ProductRepositoryInterface;
use App\Product\Domain\Product;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class ProductRepository implements ProductRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?string $filter = null): array
    {
        return $this->dataGridLoader->load('vw_producto', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Product $product): void
    {
        $entity = new ProductEntity();
        $entity->mapFromDomainModel($product);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Product $product): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $product->producto_id);
        $entity->mapFromDomainModel($product);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}