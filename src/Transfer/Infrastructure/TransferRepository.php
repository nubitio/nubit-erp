<?php

namespace App\Transfer\Infrastructure;

use App\Transfer\Domain\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class TransferRepository implements TransferRepositoryInterface
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
        return $this->dataGridLoader->load('vw_transfer', 'created_at', 'DESC', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Transfer $transfer): void
    {
        $entity = new TransferEntity();
        $entity->mapFromDomainModel($transfer);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Transfer $transfer): void
    {
        $entity = $this->entityManager->find(TransferEntity::class, $transfer->transfer_id);
        $entity->mapFromDomainModel($transfer);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(TransferEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}