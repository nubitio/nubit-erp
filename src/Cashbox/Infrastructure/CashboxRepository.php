<?php

namespace App\Cashbox\Infrastructure;

use App\Cashbox\Domain\CashboxRepositoryInterface;
use App\Cashbox\Domain\Cashbox;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CashboxRepository implements CashboxRepositoryInterface
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
        return $this->dataGridLoader->load('caja', 'apertura', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Cashbox $cashbox): void
    {
        $entity = new CashboxEntity();
        $entity->mapFromDomainModel($cashbox);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Cashbox $cashbox): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $cashbox->caja_id);
        $entity->mapFromDomainModel($cashbox);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}