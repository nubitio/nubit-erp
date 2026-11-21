<?php

declare(strict_types=1);

namespace App\Identity\Directory;

/**
 * What the users grid asked for: filter criteria, sort, search and the page. Read from the request by
 * {@see GridQueryParser}.
 */
final readonly class DirectoryQuery
{
    public const int DEFAULT_PER_PAGE = 30;
    public const int MAX_PER_PAGE = 100;

    /**
     * @param list<array{0: string, 1: string, 2: mixed}> $leaves every criterion, all of them ANDed
     * @param list<array{selector: string, desc: bool}>   $sort
     * @param list<string>                                $searchFields
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public array $leaves = [],
        public array $sort = [],
        public ?string $search = null,
        public array $searchFields = [],
    ) {}

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Whether people with this status are wanted: `status = invited` leaves out the users,
     * `status <> invited` the invitations. Any other operator on the status is ignored.
     */
    public function wants(string $status): bool
    {
        foreach ($this->leaves as [$field, $operator, $value]) {
            if ($field !== 'status') {
                continue;
            }
            if ($operator === '=' && $value !== $status) {
                return false;
            }
            if (in_array($operator, ['<>', '!='], strict: true) && $value === $status) {
                return false;
            }
        }

        return true;
    }

    /**
     * The `filters` context for the users' own grid filter: everything but `status`, which is not a
     * column of the user table.
     *
     * @return array<string, mixed>
     */
    public function userFilters(): array
    {
        $filters = [];
        $leaves = array_values(array_filter($this->leaves, static fn(array $leaf): bool => $leaf[0] !== 'status'));
        if ($leaves !== []) {
            $filters['filter'] = json_encode($leaves, JSON_THROW_ON_ERROR);
        }
        if ($this->sort !== []) {
            $filters['sort'] = json_encode($this->sort, JSON_THROW_ON_ERROR);
        }
        if ($this->search !== null && $this->searchFields !== []) {
            $filters['searchValue'] = $this->search;
            $filters['searchExpr'] = $this->searchFields;
        }

        return $filters;
    }
}
