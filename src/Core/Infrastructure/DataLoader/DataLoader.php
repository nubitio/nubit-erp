<?php

namespace App\Core\Infrastructure\DataLoader;

use ApiPlatform\Doctrine\Orm\Paginator as ApiPlatformPaginator;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\DataLoader\SortRule;
use App\Core\Domain\Util\DataMapper;
use App\Core\Domain\Util\ReflectionUtil;
use App\Core\Infrastructure\DataLoader\Paginator\Paginator;
use App\Core\Infrastructure\Entity\AuditableEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * @implements DataLoaderInterface<object>
 */
readonly class DataLoader implements DataLoaderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function loadCollection(LoadOptions $options): PaginatorInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('e')
            ->from($options->entity, 'e');

        $this->fillFilterAlias($options->mapping, $options);

        $this->addJoins($queryBuilder, $options->joins);
        $this->addFilters($queryBuilder, $options->filters);
        $this->addSort($queryBuilder, $options->sort);

        if (is_subclass_of($options->entity, AuditableEntity::class)) {
            $queryBuilder->andWhere('e.deleted = false');
        }

        $query = $queryBuilder->getQuery();

        $paginator = new DoctrinePaginator($query);

        $paginator->getQuery()
            ->setFirstResult($options->skip ?? 0)
            ->setMaxResults($options->take ?? PHP_INT_MAX);

        $data = [];

        foreach ($paginator->getIterator() as $item) {
            $mappedItem = DataMapper::map($item, $options->mapping);
            $this->mapParent($options->mapping, $mappedItem);
            $data[] = $mappedItem;
        }

        return new Paginator($data, new ApiPlatformPaginator($paginator));
    }

    /**
     * @throws NonUniqueResultException
     */
    public function loadOne(LoadOptions $options): ?object
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('e')
            ->from($options->entity, 'e');

        $this->fillFilterAlias($options->mapping, $options);

        $this->addJoins($queryBuilder, $options->joins);
        $this->addFilters($queryBuilder, $options->filters);

        $query = $queryBuilder->getQuery();

        $item = $query->getOneOrNullResult();

        if (!$item) {
            return null;
        }

        $mappedItem = DataMapper::map($item, $options->mapping);
        $this->mapParent($options->mapping, $mappedItem);

        return $mappedItem;
    }

    private function mapParent(string $mapping, object $mappedItem): void
    {
        $parent = get_parent_class($mapping);
        if ($parent) {
            foreach (array_keys(get_object_vars($mappedItem)) as $key) {
                $type = ReflectionUtil::getPropertyType($mappedItem, $key);
                if ($parent === $type) {
                    DataMapper::map($mappedItem->$key, $mappedItem, ['id']);
                }
            }
            $this->mapParent($parent, $mappedItem);
        }
    }

    private function fillFilterAlias(string $mapping, LoadOptions $options): void
    {
        $parent = get_parent_class($mapping);
        $alias = null;
        if ($parent) {
            $obj = new $mapping();
            foreach (array_keys(get_object_vars($obj)) as $key) {
                $type = ReflectionUtil::getPropertyType($obj, $key);
                if ($parent === $type) {
                    foreach ($options->joins as $join) {
                        $joinColumn = substr($join->entity, strpos($join->entity, '.') + 1);
                        if ($joinColumn === $key) {
                            $alias = $join->alias;
                            break;
                        }
                    }
                    foreach ($options->filters as $filter) {
                        $parentObj = new $parent();
                        if (ReflectionUtil::hasProperty($parentObj, $filter->field)) {
                            $filter->field = $alias . '.' . $filter->field;
                        }
                    }
                }
            }
            $this->fillFilterAlias($parent, $options);
        }
    }

    /**
     * @param JoinRule[] $joins
     */
    private function addJoins(QueryBuilder $queryBuilder, array $joins): void
    {
        $aliases = [];
        foreach ($joins as $join) {
            if (JoinType::INNER === $join->type) {
                $queryBuilder->innerJoin($join->entity, $join->alias);
            } else {
                $queryBuilder->leftJoin($join->entity, $join->alias);
            }
            $aliases[] = $join->alias;
        }

        $queryBuilder->addSelect($aliases);
    }

    /**
     * @param FilterRule[] $filters
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
