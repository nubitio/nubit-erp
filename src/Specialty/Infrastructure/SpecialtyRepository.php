<?php

namespace App\Specialty\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Specialty\Domain\Specialty;
use App\Specialty\Domain\SpecialtyRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class SpecialtyRepository implements SpecialtyRepositoryInterface
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

    public function getOne(int $id): ?Specialty
    {
        $entity = $this->entityManager->find(SpecialtyEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Specialty::class);
    }

    public function insert(Specialty $specialty): Specialty
    {
        $entity = DataMapper::map($specialty, SpecialtyEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Specialty::class);
    }

    public function update(Specialty $specialty): Specialty
    {
        $entity = $this->entityManager->find(SpecialtyEntity::class, $specialty->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($specialty, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Specialty::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(SpecialtyEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
