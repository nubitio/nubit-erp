<?php

namespace App\CashboxNewDetail\Infrastructure;

use App\CashboxNewDetail\Domain\CashboxDetail;
use App\CashboxNewDetail\Domain\CashboxDetailRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class CashboxDetailRepository implements CashboxDetailRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?CashboxDetail
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, CashboxDetail::class);
    }

    public function insert(CashboxDetail $cashboxNewDetail): CashboxDetail
    {
        $entity = DataMapper::map($cashboxNewDetail, CashboxDetailEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, CashboxDetail::class);
    }

    public function update(CashboxDetail $cashboxNewDetail): CashboxDetail
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $cashboxNewDetail->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($cashboxNewDetail, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, CashboxDetail::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
