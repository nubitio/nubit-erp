<?php

namespace App\VoucherType\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\VoucherType\Domain\VoucherType;
use App\VoucherType\Domain\VoucherTypeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class VoucherTypeRepository implements VoucherTypeRepositoryInterface
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

    public function getOne(string $id): ?VoucherType
    {
        /** @var ?VoucherTypeEntity $entity */
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, VoucherType::class);
    }

    public function insert(VoucherType $voucherType): VoucherType
    {
        $entity = DataMapper::map($voucherType, VoucherTypeEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, VoucherType::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(VoucherType $voucherType): VoucherType
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $voucherType->id);

        if (!$entity) {
            throw new ServiceException('No se encontró el tipo de comprobante');
        }

        $entity = DataMapper::map($voucherType, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, VoucherType::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
