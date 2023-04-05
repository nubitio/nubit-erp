<?php

namespace App\VoucherSeries\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Domain\VoucherSeriesRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class VoucherSeriesRepository implements VoucherSeriesRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /** @inheritdoc */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_local_comprobante', 'local', filter: $filter);
    }

    public function insert(VoucherSeries $voucherSeries): void
    {
        $entity = DataMapper::map($voucherSeries, VoucherSeriesEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(VoucherSeries $voucherSeries): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $voucherSeries->id);

        if (!$entity) {
            throw new ServiceException('La serie de comprobante no existe');
        }

        DataMapper::map($voucherSeries, $entity);
        $this->entityManager->flush();
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
