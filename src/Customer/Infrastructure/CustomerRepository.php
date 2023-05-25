<?php

namespace App\Customer\Infrastructure;

use App\Core\Domain\Exception\DeleteEntityException;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Customer\Domain\Customer;
use App\Customer\Domain\CustomerRepositoryInterface;
use App\PriceList\Infrastructure\PriceListEntity;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CustomerRepository implements CustomerRepositoryInterface
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

    public function getOne(string $id): ?Customer
    {
        /** @var ?CustomerEntity $entity */
        $entity = $this->entityManager->find(CustomerEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Customer::class);
    }

    public function insert(Customer $customer): Customer
    {
        /** @var CustomerEntity $entity */
        $entity = DataMapper::map($customer, CustomerEntity::class);
        $priceList = $this->entityManager->getReference(PriceListEntity::class, $customer->price_list?->id);
        $entity->price_list = $priceList;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Customer::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Customer $customer): Customer
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $customer->ruc);

        if (!$entity) {
            throw new ServiceException('El cliente no existe');
        }

        $entity = DataMapper::map($customer, $entity);
        $priceList = $this->entityManager->getReference(PriceListEntity::class, $customer->price_list?->id);
        $entity->price_list = $priceList;
        $this->entityManager->flush();

        return DataMapper::map($entity, Customer::class);
    }

    /**
     * @throws DeleteEntityException
     */
    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CustomerEntity::class, $id);

        if (!$entity) {
            return;
        }

        $sales = $this->entityManager->getRepository(SaleEntity::class)->findBy(['cliente' => $entity]);

        if (count($sales) > 0) {
            throw new DeleteEntityException('No se puede eliminar el cliente porque tiene ventas asociadas');
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
