<?php

namespace App\VoucherSeries\Infrastructure;

use App\VoucherSeries\Domain\VoucherSeriesRepositoryInterface;
use App\VoucherSeries\Domain\VoucherSeries;
use App\Infrastructure\Common\DataGridLoader;
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
    public function getAll(?string $filter = null): array
    {
        return $this->dataGridLoader->load('vw_local_comprobante', 'serie', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(VoucherSeries $voucherseries): void
    {
        $entity = new VoucherSeriesEntity();
        $entity->mapFromDomainModel($voucherseries);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(VoucherSeries $voucherseries): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $voucherseries->local_id);
        $entity->mapFromDomainModel($voucherseries);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}