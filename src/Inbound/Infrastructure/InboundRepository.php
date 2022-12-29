<?php

namespace App\Inbound\Infrastructure;

use App\Inbound\Domain\Inbound;
use App\Inbound\Domain\InboundRepositoryInterface;
use App\Infrastructure\Common\DataGridLoader;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

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
    public function insert(Inbound $inbound): void
    {
        try {
            $this->connection->beginTransaction();
            $entity = new InboundEntity();
            $entity->mapFromDomainModel($inbound);
            $entity->tipo_id = 1;
            $entity->estado = 1;
            $entity->date_created = new \DateTime();
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            foreach ($inbound->items as $item) {
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
    public function update(Inbound $inbound): void
    {
        $entity = $this->entityManager->find(InboundEntity::class, $inbound->movimiento_id);
        $entity->mapFromDomainModel($inbound);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(InboundEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
