<?php

namespace App\CashboxDetail\Infrastructure;

use App\Cashbox\Infrastructure\CashboxEntity;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\CashboxDetailRepositoryInterface;
use App\Core\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

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
     * @inheritdoc
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('caja_detalle', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws ORMException
     */
    public function insert(CashboxDetail $cashboxDetail): void
    {
        $nextIndex = $this->connection->fetchOne('SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?', [$cashboxDetail->caja_id]);
        $cashBox = $this->entityManager->getReference(CashboxEntity::class, $cashboxDetail->caja_id);
        $cashboxDetail->uid = $nextIndex;
        $entity = new CashboxDetailEntity();
        $entity->mapFromDomainModel($cashboxDetail);
        $entity->cashbox = $cashBox;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(CashboxDetail $cashboxDetail): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $cashboxDetail->id);
        $entity->mapFromDomainModel($cashboxDetail);
        $this->entityManager->flush();
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
