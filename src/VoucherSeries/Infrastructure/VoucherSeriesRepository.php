<?php

namespace App\VoucherSeries\Infrastructure;

use App\Infrastructure\Common\DataGridLoader;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Domain\VoucherSeriesRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class VoucherSeriesRepository implements VoucherSeriesRepositoryInterface
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
        return $this->dataGridLoader->load('vw_local_comprobante', 'local', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(VoucherSeries $voucherSeries): void
    {
        $entity = new VoucherSeriesEntity();
        $entity->mapFromDomainModel($voucherSeries);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(VoucherSeries $voucherSeries): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $voucherSeries->id);
        $entity->mapFromDomainModel($voucherSeries);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
