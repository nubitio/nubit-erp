<?php

namespace App\CashboxDetail\Infrastructure;

use App\CashboxDetail\Domain\CashboxDetailRepositoryInterface;
use App\CashboxDetail\Domain\CashboxDetail;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CashboxDetailRepository implements CashboxDetailRepositoryInterface
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
        return $this->dataGridLoader->load('caja_detalle', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(CashboxDetail $cashboxDetail): void
    {
        $entity = new CashboxDetailEntity();
        $entity->mapFromDomainModel($cashboxDetail);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(CashboxDetail $cashboxDetail): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $cashboxDetail->uid);
        $entity->mapFromDomainModel($cashboxDetail);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}