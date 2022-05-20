<?php

namespace App\Infrastructure\Product;

use App\Domain\Product\IProductRepository;
use App\Domain\Product\Product;
use App\Event\ProductCreatedEvent;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepository implements IProductRepository
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?string $filter = null): array
    {
        return $this->dataGridLoader->load('vw_producto', 'producto_id', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAllForExport(string $localId): array
    {
        return $this->connection->fetchAllAssociative('call sp_reporte_productos(?)', [$localId]);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getProductList(string $localId): array
    {
        $query = "
        select
            p.producto_id,
            p.codigo,
            p.descripcion,
            p.categoria_id,
            p.destino_id,
            pl.precio,
            CASE WHEN p.destino_id = 1 THEN 'COCINA' WHEN p.destino_id = 2 THEN 'BARRA' ELSE 'COCINA/BARRA' END AS destino
          from producto p
            inner join producto_local pl on pl.producto_id=p.producto_id
          where
            p.tipo_id = 2
            and p.estado = 1
            and pl.activo = 1
            and pl.local_id = ?
          order by
           p.descripcion";
        return $this->connection->fetchAllAssociative($query, [$localId]);
    }

    public function getById(int $id): array
    {
        // TODO: Implement getById() method.
    }

    public function getByName(string $name): array
    {
        // TODO: Implement getByName() method.
    }

    public function getByCategory(string $category): array
    {
        // TODO: Implement getByCategory() method.
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Product $product): void
    {
        $entity = new ProductEntity();
        $entity->fromDomain($product);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $event = new ProductCreatedEvent($product);
        $this->eventDispatcher->dispatch($event, ProductCreatedEvent::NAME);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(array $product): void
    {
        $this->connection->update('producto', $product, ['producto_id' => $product['producto_id']]);
    }

    public function delete(string $id): void
    {
        $this->connection->delete('producto', ['producto_id' => $id]);
    }
}