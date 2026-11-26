<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Auth\BranchReportScope;
use App\Sales\ApiResource\CustomerReceivable;
use App\Sales\Repository\SaleRecordRepository;
use ArrayIterator;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for GET /sales/reports/receivable-customers (#409, "Deudores").
 *
 * Aggregates SaleRecordRepository::aggregateReceivablesByCustomer into
 * CustomerReceivable rows and classifies each customer's oldest open invoice
 * by days overdue — 'current' (not yet due), then '1-30'/'31-60'/'61-90'/'90+'
 * — matching {@see SaleRecordRepository::aggregateReceivablesAging()} so the
 * per-row badge and the branch-wide aging KPI never disagree on a bucket's
 * meaning. This is deliberately a different axis than {@see AgingCalculator},
 * which buckets by age since the *sale*, not days past the *due date*.
 *
 * @implements ProviderInterface<CustomerReceivable>
 */
final readonly class CustomerReceivableProvider implements ProviderInterface
{
    private const int DEFAULT_PER_PAGE = 20;

    public function __construct(
        private SaleRecordRepository $repository,
        private RequestStack $requestStack,
        private BranchReportScope $scope,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $request = $this->requestStack->getCurrentRequest();
        $branchId = $this->scope->requireBranchId($request?->query->getString('branchId') ?: null);
        $search = $this->extractSearch(array_values($request?->query->all('filter') ?? []));

        $page = max(1, (int) $request?->query->get('page', 1));
        $perPage = max(1, min(200, (int) $request?->query->get('itemsPerPage', self::DEFAULT_PER_PAGE)));

        $rows = $this->repository->aggregateReceivablesByCustomer($branchId, $search, $page, $perPage);
        $total = $this->repository->countReceivableCustomers($branchId, $search);
        $today = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));

        $items = array_map(fn(array $row): CustomerReceivable => $this->hydrate($row, $today), $rows);

        return new TraversablePaginator(new ArrayIterator($items), $page, $perPage, $total);
    }

    /**
     * @param array{customerId: int, customerName: string, customerPhone: ?string, totalBalance: string, currency: string, earliestDueDate: ?string, pendingCount: int} $row
     */
    private function hydrate(array $row, DateTimeImmutable $today): CustomerReceivable
    {
        $item = new CustomerReceivable();
        $item->id = (string) $row['customerId'];
        $item->customerId = $row['customerId'];
        $item->customerName = $row['customerName'];
        $item->customerPhone = $row['customerPhone'];
        $item->totalBalance = $row['totalBalance'];
        $item->currency = $row['currency'];
        $item->earliestDueDate = $row['earliestDueDate'];
        $item->pendingCount = $row['pendingCount'];

        if ($row['earliestDueDate'] === null) {
            $item->agingBucket = 'current';

            return $item;
        }

        $dueDate = new DateTimeImmutable($row['earliestDueDate']);
        $daysApart = (int) $today->diff($dueDate)->days;
        $item->overdueDays = $dueDate < $today ? $daysApart : -$daysApart;
        $item->agingBucket = match (true) {
            $item->overdueDays <= 0 => 'current',
            $item->overdueDays <= 30 => '1-30',
            $item->overdueDays <= 60 => '31-60',
            $item->overdueDays <= 90 => '61-90',
            default => '90+',
        };

        return $item;
    }

    /**
     * Pulls a "contains" filter on `customerName` from the grid's `filter`
     * query param — the same shape DataGridFilter parses, kept minimal here
     * since this resource has no ORM-managed QueryBuilder for it to hook into.
     *
     * @param array<int, mixed> $gridFilters
     */
    private function extractSearch(array $gridFilters): ?string
    {
        foreach ($gridFilters as $filterParam) {
            if (is_string($filterParam)) {
                $decoded = json_decode($filterParam, associative: true);
                $filterParam = is_array($decoded) ? $decoded : null;
            }

            if (!is_array($filterParam) || ($filterParam[0] ?? null) !== 'customerName') {
                continue;
            }

            $value = $filterParam[2] ?? null;

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }
}
