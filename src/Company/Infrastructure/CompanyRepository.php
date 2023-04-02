<?php

namespace App\Company\Infrastructure;

use App\Company\Domain\Company;
use App\Company\Domain\CompanyRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Media\Infrastructure\MediaEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
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
        if ($company->logo?->id) {
            $logo = $this->entityManager->getReference(MediaEntity::class, $company->logo->id);
            $entity->logo = $logo;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Company::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Company $company): Company
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $company->empresa_id);

        if (!$entity) {
            throw new ServiceException('La empresa no existe.');
        }

        $entity = DataMapper::map($company, $entity);
        if ($company->logo?->id) {
            $logo = $this->entityManager->getReference(MediaEntity::class, $company->logo->id);
            $entity->logo = $logo;
        }
        $this->entityManager->flush();

        return DataMapper::map($entity, Company::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}