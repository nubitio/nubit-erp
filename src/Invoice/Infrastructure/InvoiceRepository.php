<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\Invoice\InvoiceCalculator;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\InvoiceRepositoryInterface;
use App\Sale\Domain\Sale;
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

    /** @inheritdoc  */
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

        return DataMapper::map($invoiceEntity, Invoice::class);
    }

    /**
     * @throws \Exception
     */
    public function create(Sale $sale): void
    {
        $localId = $this->connection->fetchOne('select local_id from almacen where almacen_id = ?', [$sale->almacen->almacen_id]);
        $invoiceCalculator = new InvoiceCalculator($sale->subtotal, $sale->descuento ?? 0.0, $sale->igv, $sale->recargo_consumo ?? 0.0);
        $invoiceAmounts = $invoiceCalculator->calculate();
        $this->connection->executeQuery('CALL sp_make_invoice(?,?,?,?,?,?,?)', [
            $sale->movimiento_id,
            $localId,
            $invoiceAmounts->getSubtotal(),
            $invoiceAmounts->getTaxedAmount(),
            $invoiceAmounts->getDiscountAmount(),
            $invoiceAmounts->getTax(),
            $invoiceAmounts->getTotal()
        ]);
    }

    public function insert(Invoice $invoice): void
    {
        $entity = DataMapper::map($invoice, InvoiceEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(Invoice $invoice): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $invoice->comprobante_id);
        DataMapper::map($invoice, $entity);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(InvoiceEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
