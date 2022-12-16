<?php

namespace App\Purchase\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\Purchase\Domain\Purchase;
use App\Purchase\Domain\PurchaseRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
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
        try {
            $this->connection->beginTransaction();
            $entity = new PurchaseEntity();
            $entity->mapFromDomainModel($purchase);
            $entity->tipo_id = 1;
            $entity->estado = 1;
            $entity->date_created = new \DateTime();
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            foreach ($purchase->items as $item) {
                $detailEntity = new SaleDetailEntity();
                $detailEntity->mapFromDomainModel($item);
                $detailEntity->movimiento_id = $entity->movimiento_id;
                $this->entityManager->persist($detailEntity);
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
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