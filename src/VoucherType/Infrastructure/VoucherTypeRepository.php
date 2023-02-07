<?php

namespace App\VoucherType\Infrastructure;

use App\VoucherType\Domain\VoucherTypeRepositoryInterface;
use App\VoucherType\Domain\VoucherType;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class VoucherTypeRepository implements VoucherTypeRepositoryInterface
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

        foreach ($paginator->getIterator() as $voucherType) {
            $data[] = DataMapper::map($voucherType, VoucherType::class);
        }

        return new Paginator($data, $paginator);
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
        dump($voucherType);
        $entity = DataMapper::map($voucherType, VoucherTypeEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        dump($entity);
        return DataMapper::map($entity, VoucherType::class);
    }

    public function update(VoucherType $voucherType): VoucherType
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $voucherType->id);
        $entity = DataMapper::map($voucherType, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, VoucherType::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(VoucherTypeEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}