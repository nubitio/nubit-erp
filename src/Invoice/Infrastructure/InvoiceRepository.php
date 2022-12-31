<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\Invoice\InvoiceCalculator;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceRepositoryInterface;
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
        return $this->dataGridLoader->load('vw_comprobante', 'created_at', 'desc', filter: $filter);
    }

    /**
     * @throws \Exception
     */
    public function getOne(string $saleId): Invoice
    {
        /** @var ?InvoiceEntity $invoiceEntity */
        $invoiceEntity = $this->entityManager->getRepository(InvoiceEntity::class)->findOneBy(['movimiento_id' => $saleId]);

        if (!$invoiceEntity) {
            throw new \Exception('Invoice not found');
        }

        return $invoiceEntity->mapToDomainModel();
    }

    /**
     * @throws \Exception
     */
    public function create(string $saleId, string $localId): void
    {
        $sale = $this->connection->executeQuery('SELECT * FROM vw_movimiento WHERE movimiento_id = ?', [$saleId])->fetchAssociative();
        if ('1' === $sale['has_invoice']) {
            return;
        }
        $invoiceCalculator = new InvoiceCalculator($sale['total'], $sale['descuento'] ?? 0.0, $sale['igv'], $sale['recargo_consumo'] ?? 0.0);
        $invoiceAmounts = $invoiceCalculator->calculate();
        $this->connection->executeQuery('update movimiento set has_invoice = 1 where movimiento_id = ?', [$saleId]);
        $this->connection->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?)', [$saleId, $localId, $invoiceAmounts->getSubtotal(), $invoiceAmounts->getTaxedAmount(), $invoiceAmounts->getDiscountAmount(), $invoiceAmounts->getTax(), $invoiceAmounts->getTotal()]);
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
