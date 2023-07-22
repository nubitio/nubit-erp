<?php

namespace App\VoucherSeries\Infrastructure;

use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Domain\VoucherSeriesRepositoryInterface;
use App\VoucherType\Infrastructure\VoucherTypeEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class VoucherSeriesRepository implements VoucherSeriesRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?VoucherSeries
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, VoucherSeries::class);
    }

    public function insert(VoucherSeries $voucherSeries): VoucherSeries
    {
        $entity = DataMapper::map($voucherSeries, VoucherSeriesEntity::class);

        $entity->local = $this->entityManager->getReference(BranchEntity::class, $voucherSeries->local?->local_id);
        $entity->tipo_comprobante = $this->entityManager->getRepository(VoucherTypeEntity::class)->findOneBy(['codigo' => $voucherSeries->tipo_comprobante?->codigo]);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, VoucherSeries::class);
    }

    public function update(VoucherSeries $voucherSeries): VoucherSeries
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $voucherSeries->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($voucherSeries, $entity);

        $entity->local = $this->entityManager->getReference(BranchEntity::class, $voucherSeries->local?->local_id);
        $entity->tipo_comprobante = $this->entityManager->getReference(VoucherTypeEntity::class, $voucherSeries->tipo_comprobante?->id);

        $this->entityManager->flush();

        return DataMapper::map($entity, VoucherSeries::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(VoucherSeriesEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
