<?php

namespace App\Core\Infrastructure\Common\DataLoader;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\State\Pagination\PaginatorInterface;
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

    public function load(Options $options): PaginatorInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('e')
            ->from($options->entity, 'e');

        $this->addFilters($queryBuilder, $options->filters);
        $this->addSort($queryBuilder, $options->sort);

        $query = $queryBuilder->getQuery();

        $paginator = new DoctrinePaginator($query);

        $paginator->getQuery()
            ->setFirstResult($options->rows * ($options->page - 1))
            ->setMaxResults($options->rows);

        return new Paginator($paginator);
    }

    /**
     * @param FilterInterface[] $filters
     */
    private function addFilters(QueryBuilder $queryBuilder, array $filters): void
    {
        foreach ($filters as $filter) {
            match ($filter->op) {
                FilterOperation::Contains, FilterOperation::BeginsWith, FilterOperation::EndsWith => $queryBuilder->andWhere($queryBuilder->expr()->like('e.' . $filter->field, ':value')),
                FilterOperation::Equals => $queryBuilder->andWhere($queryBuilder->expr()->eq('e.' . $filter->field, ':value')),
                FilterOperation::NotEquals => $queryBuilder->andWhere($queryBuilder->expr()->neq('e.' . $filter->field, ':value')),
                FilterOperation::LessThan => $queryBuilder->andWhere($queryBuilder->expr()->lt('e.' . $filter->field, ':value')),
                FilterOperation::LessThanOrEqual => $queryBuilder->andWhere($queryBuilder->expr()->lte('e.' . $filter->field, ':value')),
                FilterOperation::GreaterThan => $queryBuilder->andWhere($queryBuilder->expr()->gt('e.' . $filter->field, ':value')),
                FilterOperation::GreaterThanOrEqual => $queryBuilder->andWhere($queryBuilder->expr()->gte('e.' . $filter->field, ':value')),
                FilterOperation::In => $queryBuilder->andWhere($queryBuilder->expr()->in('e.' . $filter->field, ':value')),
                FilterOperation::NotIn => $queryBuilder->andWhere($queryBuilder->expr()->notIn('e.' . $filter->field, ':value')),
                FilterOperation::IsNull => $queryBuilder->andWhere($queryBuilder->expr()->isNull('e.' . $filter->field)),
                FilterOperation::IsNotNull => $queryBuilder->andWhere($queryBuilder->expr()->isNotNull('e.' . $filter->field)),
            };

            if ($filter->op === FilterOperation::Contains) {
                $queryBuilder->setParameter('value', '%' . $filter->value . '%');
            } elseif ($filter->op === FilterOperation::BeginsWith) {
                $queryBuilder->setParameter('value', $filter->value . '%');
            } elseif ($filter->op === FilterOperation::EndsWith) {
                $queryBuilder->setParameter('value', '%' . $filter->value);
            } else {
                $queryBuilder->setParameter('value', $filter->value);
            }
        }
    }

    private function addSort(QueryBuilder $queryBuilder, array $sort): void
    {
        foreach ($sort as $sortItem) {
            $queryBuilder->addOrderBy("e.{$sortItem->field}", $sortItem->order);
        }
    }
}