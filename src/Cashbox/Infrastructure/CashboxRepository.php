<?php

namespace App\Cashbox\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxRepositoryInterface;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
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

    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_caja', 'apertura', 'desc', filter: $filter);
    }

    public function getOne(string $id): Cashbox
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);

        return DataMapper::map($entity, Cashbox::class);
    }

    public function getLast(string $localId, int $userId): ?Cashbox
    {
        $entity = $this->entityManager->getRepository(CashboxEntity::class)->findOneBy([
            'local_id'   => $localId,
            'usuario_id' => $userId,
            'estado'     => 1,
        ]);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Cashbox::class);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getInfoByReceiptType(string $id): array
    {
        $sql = '
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
        ';

        return $this->connection->fetchAllAssociative($sql, [$id]);
    }

    public function insert(Cashbox $cashbox): void
    {
        $entity = DataMapper::map($cashbox, CashboxEntity::class);
        $entity->local = $this->entityManager->getReference(BranchEntity::class, $cashbox->local_id);
        $entity->user = $this->entityManager->getReference(UserEntity::class, $cashbox->usuario_id);
        foreach ($entity->details as $detail) {
            $detail->cashbox = $entity;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(Cashbox $cashbox): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $cashbox->caja_id);
        DataMapper::map($cashbox, $entity);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function updateValues(string $localId, int $userId): void
    {
        $cashBox = $this->getLast($localId, $userId);
        if ($cashBox) {
            $cashBoxId = $cashBox->caja_id;
            $startDate = $cashBox->apertura->format('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s');
            $cashBoxEntity = $this->entityManager->getReference(CashboxEntity::class, $cashBoxId);

            $totalSales = $this->getTotalSales($userId, $startDate, $endDate);

            foreach ($totalSales as $totalSale) {
                $cashBoxDetail = $this->entityManager->getRepository(CashboxDetailEntity::class)->findOneBy([
                    'caja_id'     => $cashBoxId,
                    'descripcion' => $totalSale['name'],
                ]);

                if ($cashBoxDetail) {
                    $cashBoxDetail->monto = $totalSale['total'];
                    $this->entityManager->flush();
                    continue;
                }

                $nextIndex = $this->connection->fetchOne('SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?', [$cashBoxId]);

                $cashBoxDetail = new CashboxDetailEntity();
                $cashBoxDetail->cashbox = $cashBoxEntity;
                $cashBoxDetail->uid = $nextIndex;
                $cashBoxDetail->tipo_id = 1;
                $cashBoxDetail->descripcion = $totalSale['name'];
                $cashBoxDetail->monto = $totalSale['total'];

                $this->entityManager->persist($cashBoxDetail);
                $this->entityManager->flush();
            }
        }
    }

    private function getTotalSales(int $userId, string $startDate, string $endDate): array
    {
        $sql = '
            SELECT
                pt.id,
                pt.`name`,
                SUM( ROUND( precio_total - ( precio_total * IFNULL( m.descuento, 0 ) / 100 ), 2 ) ) as total
            FROM
                movimiento_detalle md
            INNER JOIN movimiento m ON m.movimiento_id = md.movimiento_id
            INNER JOIN payment_type pt ON pt.id = m.tipo_pago 
                AND m.estado = 1 
                AND m.numero > 0 
            WHERE
                m.created_by = ?
                AND (m.date_created BETWEEN ? AND ?) 
            GROUP BY
                pt.id
        ';

        return $this->connection->fetchAllAssociative($sql, [$userId, $startDate, $endDate]);
    }
}
