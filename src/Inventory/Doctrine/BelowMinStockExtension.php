<?php

declare(strict_types=1);

namespace App\Inventory\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Inventory\Entity\ProductStock;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds WHERE ps.stock <= ps.minStock AND ps.minStock > 0
 * when the request contains ?belowMin=1 (or =true).
 *
 * Usage:
 *   GET /api/product-stocks?belowMin=1
 */
final readonly class BelowMinStockExtension implements QueryCollectionExtensionInterface
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
        if ($resourceClass !== ProductStock::class) {
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

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->andWhere("{$alias}.stock <= {$alias}.minStock")->andWhere("{$alias}.minStock > 0");
    }
}
