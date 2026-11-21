<?php

declare(strict_types=1);

namespace App\Identity\Directory;

use Nubit\ApiPlatform\Doctrine\Filter\GridFilterHelper;

/**
 * The criteria of a grid `filter` parameter, flattened the way the grid filter reads them: a leaf is
 * `[field, operator, value?]`, a list holds leaves or further lists, and connectors such as "and"
 * carry nothing.
 */
final class FilterLeaves
{
    /** @return list<array{0: string, 1: string, 2: mixed}> */
    public static function from(mixed $filter): array
    {
        $leaves = [];
        self::collect(self::decode($filter), $leaves);

        return $leaves;
    }

    /** @return array<int, mixed> */
    public static function decode(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, associative: true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Flattens the criteria the way the grid filter reads them: a leaf is `[field, operator, value?]`,
     * a list holds leaves or further lists, and connectors such as "and" carry nothing.
     *
     * @param array<int, mixed>                              $filter
     * @param list<array{0: string, 1: string, 2: mixed}> $leaves
     */
    private static function collect(array $filter, array &$leaves): void
    {
        if (self::isLeaf($filter)) {
            $filter = [$filter];
        }

        foreach ($filter as $criterion) {
            if (is_string($criterion)) {
                $decoded = json_decode($criterion, associative: true);
                if (!is_array($decoded)) {
                    continue;
                }
                $criterion = array_values($decoded);
            }
            if (!is_array($criterion) || !array_key_exists(0, $criterion)) {
                continue;
            }
            if (is_array($criterion[0])) {
                self::collect(array_values($criterion), $leaves);
                continue;
            }
            if (!array_key_exists(1, $criterion) || !GridFilterHelper::isOperator($criterion[1])) {
                continue;
            }
            if (!array_key_exists(2, $criterion) && !GridFilterHelper::isUnaryOperator($criterion[1])) {
                continue;
            }
            $leaves[] = [(string) $criterion[0], (string) $criterion[1], $criterion[2] ?? null];
        }
    }

    /** @param array<int, mixed> $candidate */
    private static function isLeaf(array $candidate): bool
    {
        if (!array_key_exists(0, $candidate) || !array_key_exists(1, $candidate) || !is_string($candidate[0])) {
            return false;
        }
        if (!GridFilterHelper::isOperator($candidate[1])) {
            return false;
        }

        return GridFilterHelper::isUnaryOperator($candidate[1]) ? count($candidate) === 2 : count($candidate) === 3;
    }
}
