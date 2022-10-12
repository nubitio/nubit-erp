<?php

namespace App\VoucherType\Infrastructure;

use App\VoucherType\Domain\VoucherTypeRepositoryInterface;
use App\VoucherType\Domain\VoucherType;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class VoucherTypeRepository implements VoucherTypeRepositoryInterface
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
        return $this->dataGridLoader->load('tipo_comprobante', 'descripcion', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(VoucherType $voucherType): void
    {
        $entity = new VoucherTypeEntity();
        $entity->mapFromDomainModel($voucherType);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(VoucherType $voucherType): void
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $voucherType->codigo);
        $entity->mapFromDomainModel($voucherType);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}