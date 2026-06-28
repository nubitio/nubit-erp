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
 * Filters stock items by stock health when ?stockStatus=low|out|ok is present.
 */
final readonly class StockItemStatusExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

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

        $status = strtolower(trim($request->query->getString('stockStatus')));
        if (!in_array($status, ['low', 'out', 'ok'], true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $prodAlias = $queryNameGenerator->generateJoinAlias('product');

        $queryBuilder->join("{$alias}.product", $prodAlias);

        match ($status) {
            'out' => $queryBuilder->andWhere("{$alias}.qtyOnHand <= 0"),
            'low' => $queryBuilder
                ->andWhere("{$prodAlias}.minStock IS NOT NULL")
                ->andWhere("{$prodAlias}.minStock > 0")
                ->andWhere("{$alias}.qtyOnHand <= {$prodAlias}.minStock"),
            'ok' => $queryBuilder
                ->andWhere(
                    "{$alias}.qtyOnHand > 0 AND (
                        {$prodAlias}.minStock IS NULL
                        OR {$prodAlias}.minStock <= 0
                        OR {$alias}.qtyOnHand > {$prodAlias}.minStock
                    )",
                ),
        };
    }
}