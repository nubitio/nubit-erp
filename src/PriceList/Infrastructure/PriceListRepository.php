<?php

namespace App\PriceList\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\PriceList\Domain\PriceList;
use App\PriceList\Domain\PriceListRepositoryInterface;
use App\PriceListDetail\Domain\PriceListDetail;
use App\PriceListDetail\Infrastructure\PriceListDetailEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class PriceListRepository implements PriceListRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(string $id): ?PriceList
    {
        /** @var ?PriceListEntity $entity */
        $entity = $this->entityManager->find(PriceListEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, PriceList::class);
    }

    public function insert(PriceList $priceList): PriceList
    {
        $entity = DataMapper::map($priceList, PriceListEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PriceList::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(PriceList $priceList): PriceList
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $priceList->id);

        if (!$entity) {
            throw new ServiceException('La lista de precios no existe.');
        }

        $entity = DataMapper::map($priceList, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PriceList::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws Exception
     */
    public function getPricesByList(int $id): array
    {
        $sql = '
            select
                p.producto_id as id,
                p.descripcion as name,
                ifnull(pld.price, p.precio) as price
            from producto p
            left join price_list_detail pld on p.producto_id = pld.product_id
            and pld.price_list_id = :id
            where p.estado = 1
            order by p.descripcion';

        return $this->connection->fetchAllAssociative($sql, ['id' => $id]);
    }

    /**
     * @param PriceListDetail[] $prices
     */
    public function savePrices(array $prices): void
    {
        foreach ($prices as $priceItem) {
            /** @var ?PriceListDetailEntity $entity */
            $entity = $this->entityManager->getRepository(PriceListDetailEntity::class)
                ->findOneBy(['price_list_id' => $priceItem->price_list_id, 'product_id' => $priceItem->product_id]);

            if ($entity) {
                $entity->price = $priceItem->price;
            } else {
                $entity = DataMapper::map($priceItem, PriceListDetailEntity::class);
            }

            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }
}
