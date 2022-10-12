<?php

namespace App\Purchase\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\Purchase\Domain\Purchase;
use App\Purchase\Domain\PurchaseRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class PurchaseRepository implements PurchaseRepositoryInterface
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
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_movimiento', 'date_created', 'DESC', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Purchase $purchase): void
    {
        $entity = new PurchaseEntity();
        $entity->mapFromDomainModel($purchase);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Purchase $purchase): void
    {
        $entity = $this->entityManager->find(PurchaseEntity::class, $purchase->movimiento_id);
        $entity->mapFromDomainModel($purchase);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(PurchaseEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}