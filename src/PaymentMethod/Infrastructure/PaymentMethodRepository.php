<?php

namespace App\PaymentMethod\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\PaymentMethod\Domain\PaymentMethod;
use App\PaymentMethod\Domain\PaymentMethodRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class PaymentMethodRepository implements PaymentMethodRepositoryInterface
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

    public function getOne(int $id): ?PaymentMethod
    {
        /** @var ?PaymentMethodEntity $entity */
        $entity = $this->entityManager->find(PaymentMethodEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, PaymentMethod::class);
    }

    public function insert(PaymentMethod $paymentMethod): PaymentMethod
    {
        $entity = DataMapper::map($paymentMethod, PaymentMethodEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PaymentMethod::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(PaymentMethod $paymentMethod): PaymentMethod
    {
        $entity = $this->entityManager->find(PaymentMethodEntity::class, $paymentMethod->id);

        if (!$entity) {
            throw new ServiceException('El método de pago no existe.');
        }

        $entity = DataMapper::map($paymentMethod, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PaymentMethod::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PaymentMethodEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}