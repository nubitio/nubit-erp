<?php

namespace App\CashboxDetail\Infrastructure;

use App\Cashbox\Infrastructure\CashboxEntity;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\CashboxDetailRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('caja_detalle', 'descripcion', filter: $filter);
    }

    /**
     * @throws Exception
     * @throws ORMException
     */
    public function insert(CashboxDetail $cashboxDetail): void
    {
        /** @var false|bool|float|int|string|null $nextIndex */
        $nextIndex = $this->connection->fetchOne('SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?', [$cashboxDetail->caja_id]);
        if (!$nextIndex) {
            throw new ORMException('No se pudo obtener el siguiente índice');
        }
        $cashBox = $this->entityManager->getReference(CashboxEntity::class, $cashboxDetail->caja_id);
        $cashboxDetail->uid = intval($nextIndex);
        $entity = DataMapper::map($cashboxDetail, CashboxDetailEntity::class);
        $entity->cashbox = $cashBox;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws ServiceException
     */
    public function update(CashboxDetail $cashboxDetail): void
    {
        $entity = $this->entityManager->find(CashboxDetailEntity::class, $cashboxDetail->id);

        if (!$entity) {
            throw new ServiceException('Detalle de caja no encontrado');
        }

        DataMapper::map($cashboxDetail, $entity);
        $this->entityManager->flush();
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
