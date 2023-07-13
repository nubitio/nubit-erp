<?php

namespace App\Cashbox\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\CashboxRepositoryInterface;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\User\Infrastructure\UserEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
     * @param array<int , array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getAll(array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_caja', 'apertura', 'desc', filter: $filter);
    }

    /**
     * @throws ServiceException
     */
    public function getOne(string $id): Cashbox
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);

        if (!$entity) {
            throw new ServiceException('Caja no encontrada');
        }

        return DataMapper::map($entity, Cashbox::class);
    }

    public function getLast(string $localId, int $userId): ?Cashbox
    {
        $entity = $this->entityManager->getRepository(CashboxEntity::class)->findOneBy([
            'local_id' => $localId,
            'usuario_id' => $userId,
            'estado' => 1,
        ]);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Cashbox::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
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
        /** @var CashboxEntity $entity */
        $entity = DataMapper::map($cashbox, CashboxEntity::class);
        $entity->local = $this->entityManager->getReference(BranchEntity::class, $cashbox->local_id);
        $entity->user = $this->entityManager->getReference(UserEntity::class, $cashbox->usuario_id);
        foreach ($entity->details as $detail) {
            $detail->cashbox = $entity;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws ServiceException
     */
    public function update(Cashbox $cashbox): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $cashbox->caja_id);

        if (!$entity) {
            throw new ServiceException('La caja no existe');
        }

        $details = $entity->details;
        $entity = DataMapper::map($cashbox, $entity);
        $entity->details = $details;

        /** @var CashboxDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->cashbox = $entity;
        }

        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CashboxEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function updateValues(string $localId, int $userId): void
    {
        $cashBox = $this->getLast($localId, $userId);
        if ($cashBox) {
            $cashBoxId = $cashBox->caja_id;
            $startDate = strval($cashBox->apertura?->format('Y-m-d H:i:s'));
            $endDate = date('Y-m-d H:i:s');
            $cashBoxEntity = $this->entityManager->getReference(CashboxEntity::class, $cashBoxId);

            $totalSales = $this->getTotalSales($userId, $startDate, $endDate);

            foreach ($totalSales as $totalSale) {
                /** @var ?CashboxDetailEntity $cashBoxDetail */
                $cashBoxDetail = $this->entityManager->getRepository(CashboxDetailEntity::class)->findOneBy([
                    'caja_id' => $cashBoxId,
                    'descripcion' => $totalSale['name'],
                ]);

                if (null != $cashBoxDetail) {
                    $cashBoxDetail->monto = floatval($totalSale['total']);
                    $this->entityManager->flush();
                    continue;
                }

                /** @var false|int $nextIndex */
                $nextIndex = $this->connection->fetchOne('SELECT IFNULL(MAX(uid), 0) + 1 FROM caja_detalle WHERE caja_id = ?', [$cashBoxId]);

                $cashBoxDetail = new CashboxDetailEntity();
                $cashBoxDetail->cashbox = $cashBoxEntity;
                $cashBoxDetail->uid = intval($nextIndex);
                $cashBoxDetail->tipo_id = 1;
                $cashBoxDetail->descripcion = strval($totalSale['name']);
                $cashBoxDetail->monto = floatval($totalSale['total']);

                $this->entityManager->persist($cashBoxDetail);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * @return array<int, array<string, bool|float|int|string|null>>
     *
     * @throws Exception
     */
    private function getTotalSales(int $userId, string $startDate, string $endDate): array
    {
        $sql = '
            SELECT
                pt.id,
                pt.`name`,
                SUM( ROUND( IFNULL( precio_total - ( precio_total * IFNULL( m.descuento, 0 ) / 100 ), 0 ), 2 ) ) AS total
            FROM
                payment_type pt
                LEFT JOIN movimiento m ON pt.id = m.tipo_pago
                LEFT JOIN movimiento_detalle md ON m.movimiento_id = md.movimiento_id
                AND m.estado = 1
                AND m.numero > 0
                AND m.created_by = ?
                AND ( m.date_created BETWEEN ? AND ? )
            GROUP BY
                pt.id,
                pt.`name`;
        ';

        /** @var array<int, array<string, bool|float|int|string|null>> $data */
        $data = $this->connection->fetchAllAssociative($sql, [$userId, $startDate, $endDate]);

        return $data;
    }
}
