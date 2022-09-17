<?php

namespace App\Infrastructure\Category;

use App\Domain\Category\ICategoryRepository;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryRepository implements ICategoryRepository
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
        $table = "
            SELECT
                c.*,
                p.descripcion AS padre
            FROM categoria c
            LEFT JOIN categoria p ON c.parent_id = p.categoria_id
        ";
        return $this->dataGridLoader->load("($table) t", 'categoria_id', filter: $filter);
    }
}