<?php

namespace App\Outbound\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Outbound\Domain\Outbound;
use App\Outbound\Domain\OutboundRepositoryInterface;
use App\Product\Infrastructure\ProductEntity;
use App\Sale\Infrastructure\SaleEntity;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

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

    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_movimiento', 'date_created', 'DESC', filter: $filter);
    }

    /**
     * @throws Exception
     * @throws ORMException
     */
    public function insert(Outbound $outbound): void
    {
        try {
            $this->connection->beginTransaction();
            $entity = DataMapper::map($outbound, OutboundEntity::class);
            $entity->tipo_id = 2;
            $entity->estado = true;
            $entity->date_created = new \DateTime();
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $ref = $this->entityManager->getReference(SaleEntity::class, $entity->movimiento_id);

            if (null === $outbound->items) {
                throw new \Exception('No se encontraron productos');
            }

            foreach ($outbound->items as $item) {
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

    public function update(Outbound $outbound): void
    {
        /** @var OutboundEntity $entity */
        $entity = $this->entityManager->find(OutboundEntity::class, $outbound->movimiento_id);
        DataMapper::map($outbound, $entity);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(OutboundEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
