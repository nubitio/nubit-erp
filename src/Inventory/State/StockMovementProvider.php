<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Repository\StockMovementRepository;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for GET /stock-movements (Kardex).
 *
 * Reads filters from the request query string and delegates to
 * StockMovementRepository which executes the window-function query.
 *
 * Pagination is handled via ApiPlatform's ArrayPaginator so that
 * the standard hydra:totalItems / hydra:view envelope is preserved.
 *
 * @implements ProviderInterface<object>
 */
final readonly class StockMovementProvider implements ProviderInterface
{
    private const int DEFAULT_PER_PAGE = 50;

    public function __construct(
        private StockMovementRepository $repository,
        private RequestStack $requestStack,
    ) {
    }

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $request = $this->requestStack->getCurrentRequest();

        $filters = [
            'warehouse' => $request?->query->get('warehouse'),
            'product'   => $request?->query->get('product'),
            'type'      => $request?->query->get('type') ?? $request?->query->get('movementType'),
            'from'      => $request?->query->get('from'),
            'to'        => $request?->query->get('to'),
        ];

        // Remove nulls
        $filters = array_filter($filters, static fn ($v) => $v !== null && $v !== '');

        $page    = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $perPage = max(1, min(200, (int) ($request?->query->get('itemsPerPage', self::DEFAULT_PER_PAGE) ?? self::DEFAULT_PER_PAGE)));

        $items = $this->repository->findKardex($filters, $page, $perPage);
        $total = $this->repository->countKardex($filters);

        return new TraversablePaginator(new \ArrayIterator($items), $page, $perPage, $total);
    }
}
