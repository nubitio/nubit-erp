<?php

namespace App\Branch\Infrastructure;

use App\Branch\Domain\Branch;
use App\Branch\Domain\BranchRepositoryInterface;
use App\Company\Infrastructure\CompanyEntity;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class BranchRepository implements BranchRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Branch
    {
        /** @var ?BranchEntity $entity */
        $entity = $this->entityManager->find(BranchEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Branch::class);
    }

    public function insert(Branch $branch): Branch
    {
        $entity = DataMapper::map($branch, BranchEntity::class);
        $company = $this->entityManager->getReference(CompanyEntity::class, $branch->empresa->empresa_id);
        $entity->empresa = $company;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Branch::class);
    }

    public function update(Branch $branch): Branch
    {
        $entity = $this->entityManager->find(BranchEntity::class, $branch->local_id);
        $entity = DataMapper::map($branch, $entity);
        $company = $this->entityManager->getReference(CompanyEntity::class, $branch->empresa->empresa_id);
        $entity->empresa = $company;
        $this->entityManager->flush();

        return DataMapper::map($entity, Branch::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(BranchEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}