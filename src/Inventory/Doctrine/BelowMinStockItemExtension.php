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
 * Adds a JOIN-based filter on StockItem collections when ?belowMin=1 is present.
 *
 * Produces: WHERE si.qtyOnHand < p.minStock AND p.minStock IS NOT NULL AND p.minStock > 0
 *
 * Uses the V2 stock_items table (source of truth) joined to producto.stock_minimo.
 *
 * Usage:
 *   GET /api/stock-items?belowMin=1
 */
final readonly class BelowMinStockItemExtension implements QueryCollectionExtensionInterface
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

        $belowMin = $request->query->get('belowMin');
        if ($belowMin !== '1' && $belowMin !== 'true') {
            return;
        }

        $alias    = $queryBuilder->getRootAliases()[0];
        $prodAlias = $queryNameGenerator->generateJoinAlias('product');

        $queryBuilder
            ->join("{$alias}.product", $prodAlias)
            ->andWhere("{$alias}.qtyOnHand < {$prodAlias}.minStock")
            ->andWhere("{$prodAlias}.minStock IS NOT NULL")
            ->andWhere("{$prodAlias}.minStock > 0");
    }
}
