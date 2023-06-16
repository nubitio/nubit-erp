<?php

namespace App\Production\Infrastructure;

use App\ComboOption\Infrastructure\ComboOptionEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Employee\Infrastructure\EmployeeEntity;
use App\Product\Infrastructure\ProductEntity;
use App\Production\Domain\Production;
use App\Production\Domain\ProductionRepositoryInterface;
use App\ProductionDetail\Infrastructure\ProductionDetailEntity;
use App\Recipe\Infrastructure\RecipeEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class ProductionRepository implements ProductionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Production
    {
        $entity = $this->entityManager->find(ProductionEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Production::class);
    }

    /**
     * @throws ORMException
     */
    public function insert(Production $production): Production
    {
        $entity = DataMapper::map($production, ProductionEntity::class);

        $entity->product = $this->entityManager->getReference(ProductEntity::class, $production->product?->producto_id);
        $entity->recipe = $this->entityManager->getReference(RecipeEntity::class, $production->recipe?->id);
        $entity->employee = $this->entityManager->getReference(EmployeeEntity::class, $production->employee?->id);
        $entity->production_line = $this->entityManager->getReference(ComboOptionEntity::class, $production->production_line?->id);

        /** @var ProductionDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->production = $entity;
            $detail->material = $this->entityManager->getReference(ProductEntity::class, $detail->material?->producto_id);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Production::class);
    }

    /**
     * @throws ORMException
     * @throws ServiceException
     */
    public function update(Production $production): Production
    {
        $entity = $this->entityManager->find(ProductionEntity::class, $production->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($production, $entity);

        $entity->product = $this->entityManager->getReference(ProductEntity::class, $production->product?->producto_id);
        $entity->recipe = $this->entityManager->getReference(RecipeEntity::class, $production->recipe?->id);
        $entity->employee = $this->entityManager->getReference(EmployeeEntity::class, $production->employee?->id);
        $entity->production_line = $this->entityManager->getReference(ComboOptionEntity::class, $production->production_line?->id);

        $this->deleteDetails($entity);
        /** @var ProductionDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->production = $entity;
            $detail->material = $this->entityManager->getReference(ProductEntity::class, $detail->material?->producto_id);
        }

        $this->entityManager->flush();

        return DataMapper::map($entity, Production::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(ProductionEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->deleteDetails($entity);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function deleteDetails(ProductionEntity $entity): void
    {
        $this->entityManager->createQuery('DELETE FROM App\ProductionDetail\Infrastructure\ProductionDetailEntity pd WHERE pd.production = :production')
            ->setParameter('production', $entity)
            ->execute();
    }
}
