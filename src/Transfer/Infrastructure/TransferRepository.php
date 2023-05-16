<?php

namespace App\Transfer\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Transfer\Domain\Transfer;
use App\Transfer\Domain\TransferRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class TransferRepository implements TransferRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_transfer', 'created_at', 'DESC', filter: $filter);
    }

    public function insert(Transfer $transfer): void
    {
        $entity = DataMapper::map($transfer, TransferEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(Transfer $transfer): void
    {
        $entity = $this->entityManager->find(TransferEntity::class, $transfer->transfer_id);

        if (!$entity) {
            throw new ServiceException('La transferencia no existe');
        }

        DataMapper::map($transfer, $entity);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(TransferEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
