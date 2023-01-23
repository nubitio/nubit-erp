<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use App\Invoice\Domain\Invoice;
use App\Invoice\Infrastructure\InvoiceEntity;
use App\Sale\Domain\Sale;
use App\Sale\Domain\SaleRepositoryInterface;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class SaleRepository implements SaleRepositoryInterface
{
    public function __construct(
        private Connection             $connection,
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $sale) {
            /** @var Sale $sale */
            $sale = DataMapper::map($sale, Sale::class);
            /** @var ?InvoiceEntity $invoice */
            $invoice = $this->entityManager->getRepository(InvoiceEntity::class)->findBy(['movimiento_id' => $sale->movimiento_id]);
            $sale->invoice = $invoice ? DataMapper::map($invoice, Invoice::class) : null;

            $data[] = $sale;
        }

        return new EasyStore($paginator->getTotalItems(), $data);
    }

    /**
     * @throws Exception
     */
    public function getOneById(string $id): array
    {
        $header = $this->connection->executeQuery('CALL sp_sale_header(?)', [$id])->fetchAssociative();
        $details = $this->connection->executeQuery('CALL sp_sale_detail(?)', [$id])->fetchAllAssociative();

        return [
            'header'  => $header,
            'details' => $details,
        ];
    }

    public function getOne(string $id): ?Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->getRepository(SaleEntity::class)->find($id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Sale::class);
    }

    public function insert(Sale $sale): Sale
    {
        $entity = DataMapper::map($sale, SaleEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        foreach ($sale->items as $item) {
            $detailEntity = new SaleDetailEntity();
            $detailEntity->mapFromDomainModel($item);
            $detailEntity->movimiento_id = $entity->movimiento_id;
            $this->entityManager->persist($detailEntity);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return DataMapper::map($entity, Sale::class);
    }

    public function update(Sale $sale): Sale
    {
        /** @var SaleEntity $entity */
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity = DataMapper::map($sale, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Sale::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function void(Sale $sale): void
    {
        $entity = $this->entityManager->find(SaleEntity::class, $sale->movimiento_id);
        $entity->estado = 2;
        $entity->glosa = $sale->glosa;
        $entity->updated_by = $sale->updated_by;
        $entity->date_updated = new \DateTime();
        $this->entityManager->flush();
    }
}
