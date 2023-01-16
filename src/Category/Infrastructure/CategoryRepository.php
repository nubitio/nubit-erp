<?php

namespace App\Category\Infrastructure;

use App\Category\Domain\Category;
use App\Category\Domain\CategoryRepositoryInterface;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CategoryRepository implements CategoryRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('categoria', 'descripcion', filter: $filter);
    }

    public function findOneBy(array $filter): ?Category
    {
        /** @var ?CategoryEntity $categoryEntity */
        $entity = $this->entityManager->getRepository(CategoryEntity::class)->findOneBy($filter);

        if ($entity === null) {
            return null;
        }

        return DataMapper::map($entity, Category::class);
    }

    public function insert(Category $category): Category
    {
        $entity = DataMapper::map($category, CategoryEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Category::class);
    }

    public function update(Category $category): Category
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $category->categoria_id);
        $entity = DataMapper::map($category, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Category::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
