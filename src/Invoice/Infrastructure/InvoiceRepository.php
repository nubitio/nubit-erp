<?php

namespace App\Invoice\Infrastructure;

use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Invoice\Domain\Invoice;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceRepository implements InvoiceRepositoryInterface
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
        return $this->dataGridLoader->load('comprobante', 'created_at', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Invoice $invoice): void
    {
        $entity = new InvoiceEntity();
        $entity->mapFromDomainModel($invoice);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Invoice $invoice): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $invoice->comprobante_id);
        $entity->mapFromDomainModel($invoice);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}