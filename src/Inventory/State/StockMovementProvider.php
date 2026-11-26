<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Auth\BranchReportScope;
use App\Inventory\Repository\StockMovementRepository;
use App\Inventory\Service\KardexReferenceResolver;
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
        private KardexReferenceResolver $referenceResolver,
        private RequestStack $requestStack,
        private BranchReportScope $scope,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $request = $this->requestStack->getCurrentRequest();

        $movementType = $request?->query->getString('movementType') ?? '';
        $filters = [
            'branchId' => $this->scope->requireBranchId($request?->query->getString('branchId') ?: null),
            'warehouse' => $request?->query->getString('warehouse'),
            'product' => $request?->query->getString('product'),
            'type' => $request?->query->getString('type', $movementType),
            'from' => $request?->query->getString('from'),
            'to' => $request?->query->getString('to'),
            'valuation' => $request?->query->getString('valuation'),
        ];

        if ($request !== null) {
            $filters = array_replace($filters, $this->extractGridFilters($request->query->all('filter')));
        }

        /** @var array<string, string> $filters */
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $page = max(1, (int) ($request?->query->get('page', 1) ?? 1));
        $perPage = max(1, min(
            200,
            (int) ($request?->query->get('itemsPerPage', self::DEFAULT_PER_PAGE) ?? self::DEFAULT_PER_PAGE),
        ));

        $items = $this->repository->findKardex($filters, $page, $perPage);
        $this->referenceResolver->applyToMovements($items);
        $total = $this->repository->countKardex($filters);

        return new TraversablePaginator(new \ArrayIterator($items), $page, $perPage, $total);
    }

    /**
     * Convert DataGridView filter arrays into the compact repository filter shape.
     *
     * @param array<array-key, mixed> $gridFilters
     *
     * @return array<string, string>
     */
    private function extractGridFilters(array $gridFilters): array
    {
        $mapped = [];

        foreach ($gridFilters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = (string) ($filter[0] ?? '');
            $operator = (string) ($filter[1] ?? '=');
            $value = $filter[2] ?? null;

            if (!is_scalar($value) || $value === '') {
                continue;
            }

            $value = (string) $value;

            match ($field) {
                'warehouse', 'product' => $mapped[$field] = $value,
                'movementType' => $mapped['type'] = $value,
                'sourceId' => $this->mapTextFilter($mapped, 'sourceId', $operator, $value),
                'note' => $this->mapTextFilter($mapped, 'note', $operator, $value),
                'occurredAt' => $this->mapDateFilter($mapped, $operator, $value),
                default => null,
            };
        }

        return $mapped;
    }

    /** @param array<string, string> $mapped */
    private function mapTextFilter(array &$mapped, string $name, string $operator, string $value): null
    {
        $mapped[$name] = $value;
        $mapped[$name . 'Operator'] = $operator;

        return null;
    }

    /** @param array<string, string> $mapped */
    private function mapDateFilter(array &$mapped, string $operator, string $value): null
    {
        if (in_array($operator, ['>', '>=', '='], true)) {
            $mapped['from'] = $value;
        }

        if (in_array($operator, ['<', '<=', '='], true)) {
            $mapped['to'] = $value;
        }

        return null;
    }
}
