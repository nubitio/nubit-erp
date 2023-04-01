<?php

namespace App\Inbound\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Inbound\Domain\Inbound;
use App\Inbound\Domain\InboundRepositoryInterface;
use App\Product\Infrastructure\ProductEntity;
use App\Sale\Infrastructure\SaleEntity;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

class InboundRepository implements InboundRepositoryInterface
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

    /** @inheritdoc  */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_movimiento', 'date_created', 'DESC', filter: $filter);
    }

    /**
     * @throws Exception
     * @throws ORMException
     */
    public function insert(Inbound $inbound): void
    {
        try {
            $this->connection->beginTransaction();
            $entity = DataMapper::map($inbound, InboundEntity::class);
            $entity->tipo_id = 1;
            $entity->estado = true;
            $entity->date_created = new \DateTime();
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $ref = $this->entityManager->getReference(SaleEntity::class, $entity->movimiento_id);
            foreach ($inbound->items as $item) {
                $detailEntity = DataMapper::map($item, SaleDetailEntity::class);
                $detailEntity->movimiento = $ref;
                $detailEntity->producto = $this->entityManager->getReference(ProductEntity::class, $item->producto_id);
                $this->entityManager->persist($detailEntity);
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function update(Inbound $inbound): void
    {
        /** @var InboundEntity $entity */
        $entity = $this->entityManager->find(InboundEntity::class, $inbound->movimiento_id);
        DataMapper::map($inbound, $entity);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(InboundEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
