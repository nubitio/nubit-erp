<?php

namespace App\Sale\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

class SaleRepository implements SaleRepositoryInterface
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
     * @throws Exception
     */
    public function getOneById(string $id): array
    {
        $header = $this->connection->executeQuery('CALL sp_sale_header(?)', [$id])->fetchAssociative();
        $details = $this->connection->executeQuery('CALL sp_sale_detail(?)', [$id])->fetchAllAssociative();

        return [
            'header'  => $header,
            'details' => $details
        ];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Sale $sale): void
    {
        try {
            $this->connection->beginTransaction();
            $entity = new SaleEntity();
            $entity->mapFromDomainModel($sale);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            foreach ($sale->items as $item) {
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
    public function update(Sale $sale): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity->mapFromDomainModel($sale);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}