<?php

namespace App\Outbound\Infrastructure;

use App\Core\Infrastructure\Common\DataGridLoader;
use App\Outbound\Domain\Outbound;
use App\Outbound\Domain\OutboundRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class OutboundRepository implements OutboundRepositoryInterface
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
        return $this->dataGridLoader->load('vw_movimiento', 'date_created', 'DESC', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Outbound $outbound): void
    {
        try {
            $this->connection->beginTransaction();
            $entity = new OutboundEntity();
            $entity->mapFromDomainModel($outbound);
            $entity->tipo_id = 2;
            $entity->estado = true;
            $entity->date_created = new \DateTime();
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            foreach ($outbound->items as $item) {
                $detailEntity = new SaleDetailEntity();
                $detailEntity->mapFromDomainModel($item);
                $detailEntity->movimiento_id = $entity->movimiento_id;
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

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Outbound $outbound): void
    {
        $entity = $this->entityManager->find(OutboundEntity::class, $outbound->movimiento_id);
        $entity->mapFromDomainModel($outbound);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(OutboundEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
