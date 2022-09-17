<?php

namespace App\Sale\Infrastructure;

use App\Sale\Domain\SaleRepositoryInterface;
use App\Sale\Domain\Sale;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class SaleRepository implements SaleRepositoryInterface
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
        return $this->dataGridLoader->load('vw_movimiento', 'date_created', 'DESC', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Sale $sale): void
    {
        $entity = new SaleEntity();
        $entity->mapFromDomainModel($sale);
        $entity->tipo_id = 2;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Sale $sale): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity->mapFromDomainModel($sale);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}