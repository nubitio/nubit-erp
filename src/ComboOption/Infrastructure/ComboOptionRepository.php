<?php

namespace App\ComboOption\Infrastructure;

use App\ComboOption\Domain\ComboOption;
use App\ComboOption\Domain\ComboOptionRepositoryInterface;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use Doctrine\ORM\EntityManagerInterface;

readonly class ComboOptionRepository implements ComboOptionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?ComboOption
    {
        $entity = $this->entityManager->find(ComboOptionEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, ComboOption::class);
    }

    public function insert(ComboOption $comboOption): ComboOption
    {
        $entity = DataMapper::map($comboOption, ComboOptionEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, ComboOption::class);
    }

    public function update(ComboOption $comboOption): ComboOption
    {
        $entity = $this->entityManager->find(ComboOptionEntity::class, $comboOption->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($comboOption, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, ComboOption::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(ComboOptionEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
