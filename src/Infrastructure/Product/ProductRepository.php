<?php

namespace App\Infrastructure\Product;

use App\Domain\Product\IProductRepository;
use App\Domain\Product\Product;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;

class ProductRepository implements IProductRepository
{
    private Connection $connection;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(): array
    {
        return $this->dataGridLoader->load('producto', 'producto_id');
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
        $this->connection->insert('producto', $product);
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