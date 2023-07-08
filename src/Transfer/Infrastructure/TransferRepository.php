<?php

namespace App\Transfer\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Transfer\Domain\TransferRepositoryInterface;
use App\Transfer\Domain\Transfer;
use Doctrine\ORM\EntityManagerInterface;

readonly class TransferRepository implements TransferRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Transfer
    {
        $entity = $this->entityManager->find(TransferEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Transfer::class);
    }

    public function insert(Transfer $transfer): Transfer
    {
        $entity = DataMapper::map($transfer, TransferEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Transfer::class);
    }

    public function update(Transfer $transfer): Transfer
    {
        $entity = $this->entityManager->find(TransferEntity::class, $transfer->transfer_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($transfer, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Transfer::class);
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