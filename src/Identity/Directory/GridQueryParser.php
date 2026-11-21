<?php

declare(strict_types=1);

namespace App\Identity\Directory;

/**
 * Reads the grid query-string protocol every Nubit grid speaks (`filter`, `sort`,
 * `searchValue`/`searchExpr`, `page`, `itemsPerPage`), so the users grid needs no special client:
 * bracket syntax (`filter[0][0]=username`) and JSON-encoded strings are both accepted.
 */
final class GridQueryParser
{
    /** @param array<string, mixed> $query */
    public static function parse(array $query): DirectoryQuery
    {
        $search = $query['searchValue'] ?? null;
        $expr = $query['searchExpr'] ?? [];

        return new DirectoryQuery(
            page: max(1, (int) ($query['page'] ?? 1)),
            perPage: min(DirectoryQuery::MAX_PER_PAGE, max(
                1,
                (int) ($query['itemsPerPage'] ?? DirectoryQuery::DEFAULT_PER_PAGE),
            )),
            leaves: FilterLeaves::from($query['filter'] ?? null),
            sort: self::sort($query['sort'] ?? null),
            search: is_string($search) && $search !== '' ? $search : null,
            searchFields: array_values(array_filter(
                array_map(strval(...), is_array($expr) ? $expr : [$expr]),
                static fn(string $field): bool => $field !== '',
            )),
        );
    }

    /** @return list<array{selector: string, desc: bool}> */
    private static function sort(mixed $value): array
    {
        $sort = [];
        foreach (FilterLeaves::decode($value) as $descriptor) {
            $descriptor = is_string($descriptor) ? json_decode($descriptor, associative: true) : $descriptor;
            if (
                !is_array($descriptor)
                || !is_string($descriptor['selector'] ?? null)
                || $descriptor['selector'] === ''
            ) {
                continue;
            }
            $desc = $descriptor['desc'] ?? false;
            $sort[] = [
                'selector' => $descriptor['selector'],
                'desc' => $desc === true || in_array($desc, ['true', '1', 1], strict: true),
            ];
        }

        return $sort;
    }
}
