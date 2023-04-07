<?php

namespace App\Core\Infrastructure\DataLoader;

use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\Paginator\Paginator;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

readonly class DataLoader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @throws \Exception
     */
    public function load(Options $options): PaginatorInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('e')
            ->from($options->entity, 'e');

        $this->addJoins($queryBuilder, $options->joins);
        $this->addFilters($queryBuilder, $options->filters);
        $this->addSort($queryBuilder, $options->sort);

        $query = $queryBuilder->getQuery();

        $paginator = new DoctrinePaginator($query);

        $paginator->getQuery()
            ->setFirstResult($options->rows * ($options->page - 1))
            ->setMaxResults($options->rows);

        $data = [];

        foreach ($paginator->getIterator() as $item) {
            $data[] = DataMapper::map($item, $options->mapping);
        }

        return new Paginator($data, new ApiPlatformPaginator($paginator));
    }

    /**
     * @param JoinRule[] $joins
     */
    private function addJoins(QueryBuilder $queryBuilder, array $joins): void
    {
        $aliases = [];
        foreach ($joins as $join) {
            if ($join->type === JoinType::INNER) {
                $queryBuilder->innerJoin($join->entity, $join->alias);
            } else {
                $queryBuilder->leftJoin($join->entity, $join->alias);
            }
            $aliases[] = $join->alias;
        }

        $queryBuilder->addSelect($aliases);
    }

    /**
     * @param FilterInterface[] $filters
     */
    private function addFilters(QueryBuilder $queryBuilder, array $filters): void
    {
        foreach ($filters as $filter) {
            $filter->apply($queryBuilder);
        }
    }

    /**
     * @param SortRule[] $sort
     */
    private function addSort(QueryBuilder $queryBuilder, array $sort): void
    {
        foreach ($sort as $sortItem) {
            if (str_contains($sortItem->field, '.')) {
                $queryBuilder->addOrderBy($sortItem->field, $sortItem->order);
            } else {
                $queryBuilder->addOrderBy("e.$sortItem->field", $sortItem->order);
            }
        }
    }
}