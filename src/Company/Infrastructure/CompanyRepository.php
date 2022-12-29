<?php

namespace App\Company\Infrastructure;

use App\Company\Domain\Company;
use App\Company\Domain\CompanyRepositoryInterface;
use App\Infrastructure\Common\DataGridLoader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CompanyRepository implements CompanyRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('empresa', 'razon_social', filter: $filter);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function insert(Company $company): void
    {
        $entity = new CompanyEntity();
        $entity->mapFromDomainModel($company);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Company $company): void
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $company->empresa_id);
        $entity->mapFromDomainModel($company);
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CompanyEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
