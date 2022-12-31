<?php

namespace App\SaleDetail\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\SaleDetail\Domain\SaleDetail;
use App\SaleDetail\Domain\SaleDetailRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class SaleDetailRepository implements SaleDetailRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('movimiento_detalle', 'producto_id', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(SaleDetail $saledetail): void
    {
        $entity = new SaleDetailEntity();
        $entity->mapFromDomainModel($saledetail);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(SaleDetail $saledetail): void
    {
        $entity = $this->entityManager->find(SaleDetailEntity::class, $saledetail->movimiento_id);
        $entity->mapFromDomainModel($saledetail);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleDetailEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
