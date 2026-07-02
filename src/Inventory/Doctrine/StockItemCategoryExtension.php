<?php

declare(strict_types=1);

namespace App\Inventory\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Inventory\Entity\StockItem;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters stock items by product category when ?categoryId=<uuid> is present.
 */
final readonly class StockItemCategoryExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== StockItem::class) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $categoryId = trim($request->query->getString('categoryId'));
        if ($categoryId === '') {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $prodAlias = $queryNameGenerator->generateJoinAlias('product');
        $catAlias = $queryNameGenerator->generateJoinAlias('category');

        $queryBuilder
            ->join("{$alias}.product", $prodAlias)
            ->join("{$prodAlias}.category", $catAlias)
            ->andWhere("{$catAlias}.id = :stockItemCategoryId")
            ->setParameter('stockItemCategoryId', $categoryId);
    }
}
