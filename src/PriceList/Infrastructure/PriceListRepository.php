<?php

namespace App\PriceList\Infrastructure;

use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
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
        private Connection             $connection,
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
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

    public function update(PriceList $priceList): PriceList
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $priceList->id);
        $entity = DataMapper::map($priceList, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, PriceList::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(PriceListEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getPricesByList(int $id): array
    {
        $sql = "
            SELECT
                p.producto_id as id,
                p.descripcion as name,
                ifnull(pld.price, p.precio) as price
            FROM producto p 
            left join price_list_detail pld on p.producto_id = pld.product_id
            and pld.price_list_id = :id
            order by p.descripcion";

        return $this->connection->fetchAllAssociative($sql, ['id' => $id]);
    }

    /**
     * @param PriceListDetail[] $prices
     * @throws Exception
     */
    public function savePrices(array $prices): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->delete('price_list_detail', ['price_list_id' => $prices[0]->price_list_id]);

            foreach ($prices as $price) {
                $entity = DataMapper::map($price, PriceListDetailEntity::class);
                $this->entityManager->persist($entity);
            }

            $this->entityManager->flush();
            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}