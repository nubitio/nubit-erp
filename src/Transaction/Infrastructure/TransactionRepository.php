<?php

namespace App\Transaction\Infrastructure;

use App\Transaction\Domain\TransactionRepositoryInterface;
use App\Transaction\Domain\Transaction;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class TransactionRepository implements TransactionRepositoryInterface
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
        return $this->dataGridLoader->load('operacion', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Transaction $transaction): void
    {
        $entity = new TransactionEntity();
        $entity->mapFromDomainModel($transaction);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Transaction $transaction): void
    {
        $entity = $this->entityManager->find(TransactionEntity::class, $transaction->operacion_id);
        $entity->mapFromDomainModel($transaction);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(TransactionEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}