<?php

namespace App\Company\Infrastructure;

use App\Company\Domain\CompanyRepositoryInterface;
use App\Company\Domain\Company;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class CompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $company) {
            $data[] = DataMapper::map($company, Company::class);
        }

        return new Paginator($data, $paginator);
    }

    public function getOne(string $id): ?Company
    {
        /** @var ?CompanyEntity $entity */
        $entity = $this->entityManager->find(CompanyEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Company::class);
    }

    public function insert(Company $company): Company
    {
        $entity = DataMapper::map($company, CompanyEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Company::class);
    }

    public function update(Company $company): Company
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $company->empresa_id);
        $entity = DataMapper::map($company, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Company::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}