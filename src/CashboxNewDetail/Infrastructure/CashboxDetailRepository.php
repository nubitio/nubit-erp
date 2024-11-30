<?php

namespace App\CashboxNewDetail\Infrastructure;

use App\CashboxNew\Infrastructure\CashboxEntity;
use App\CashboxNewDetail\Domain\CashboxDetail;
use App\CashboxNewDetail\Domain\CashboxDetailRepositoryInterface;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CashboxDetailRepository implements CashboxDetailRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private Connection $connection,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?CashboxDetail
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, CashboxDetail::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     * @throws Exception
     */
    public function insert(CashboxDetail $cashboxDetail): CashboxDetail
    {
        $entity = DataMapper::map($cashboxDetail, CashboxDetailEntity::class);
        $entity->cashbox = $this->entityManager->getReference(CashboxEntity::class, $cashboxDetail->caja_id);

        /** @var false|bool|float|int|string|null $nextIndex */
        $nextIndex = $this->connection->fetchOne('SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?', [$cashboxDetail->caja_id]);

        if (!$nextIndex) {
            throw new ServiceException('No se pudo obtener el siguiente índice');
        }

        $entity->uid = intval($nextIndex);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, CashboxDetail::class);
    }

    public function update(CashboxDetail $cashboxDetail): CashboxDetail
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $cashboxDetail->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($cashboxDetail, $entity);
        $entity->cashbox = $this->entityManager->getReference(CashboxEntity::class, $cashboxDetail->caja_id);
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
