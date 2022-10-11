<?php

namespace App\Cashbox\Infrastructure;

use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxRepositoryInterface;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Infrastructure\Common\DataGridLoader;
use App\Local\Infrastructure\LocalEntity;
use App\User\Infrastructure\UserEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CashboxRepository implements CashboxRepositoryInterface
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
    public function getAll(?string $filter = null): array
    {
        return $this->dataGridLoader->load('vw_caja', 'apertura', 'desc', filter: $filter);
    }

    public function getOne(string $id): Cashbox
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);
        return $entity->mapToDomainModel();
    }

    public function getLast(string $localId, int $userId): ?Cashbox
    {
        $entity = $this->entityManager->getRepository(CashboxEntity::class)->findOneBy([
            'local_id'   => $localId,
            'usuario_id' => $userId,
            'estado'     => 1
        ]);
        return $entity ? $entity->mapToDomainModel() : null;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getInfoByReceiptType(string $id): array
    {
        $sql = "
            SELECT
                tc.descripcion AS tipo,
                c.serie,
                min( c.numero ) AS desde,
                max( c.numero ) AS hasta,
                sum( c.total - c.discount_amount ) AS total 
            FROM
                comprobante c
                INNER JOIN tipo_comprobante tc ON tc.codigo = c.tipo_comprobante_id
                INNER JOIN movimiento m ON m.movimiento_id = c.movimiento_id 
            WHERE
                m.caja_id = ? 
            GROUP BY
                tc.codigo
        ";

        return $this->connection->fetchAllAssociative($sql, [$id]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Cashbox $cashbox): void
    {
        $entity = new CashboxEntity();
        $entity->mapFromDomainModel($cashbox);
        $entity->local = $this->entityManager->getReference(LocalEntity::class, $cashbox->local_id);
        $entity->user = $this->entityManager->getReference(UserEntity::class, $cashbox->usuario_id);
        $this->entityManager->persist($entity);
        if ($cashbox->items) {
            foreach ($cashbox->items as $item) {
                $itemEntity = new CashboxDetailEntity();
                $itemEntity->mapFromDomainModel($item);
                $itemEntity->cashbox = $entity;
                $this->entityManager->persist($itemEntity);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Cashbox $cashbox): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $cashbox->caja_id);
        $entity->mapFromDomainModel($cashbox);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}