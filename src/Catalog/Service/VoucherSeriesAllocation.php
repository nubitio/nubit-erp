<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use Doctrine\DBAL\Connection;

final class VoucherSeriesAllocation
{
    /**
     * Serialize series allocations by document type in this tenant database.
     * Historical duplicates stay untouched; the lock prevents new ones even
     * before an existing tenant can add a unique index without losing data.
     *
     * @template T
     * @param callable(): T $allocate
     * @return T
     */
    public static function withTypeLock(Connection $connection, string $type, callable $allocate): mixed
    {
        return $connection->transactional(static function (Connection $connection) use ($type, $allocate): mixed {
            $connection->fetchOne('SELECT codigo FROM tipo_comprobante WHERE codigo = ? FOR UPDATE', [$type]);

            return $allocate();
        });
    }

    /** Call under the corresponding type lock. */
    public static function conflictingBranch(
        Connection $connection,
        string $type,
        string $series,
        int $exceptId = 0,
    ): ?string {
        $branchId = $connection->fetchOne(
            'SELECT local_id FROM local_comprobante
             WHERE tipo_comprobante_id = ? AND series = ? AND id <> ? ORDER BY id LIMIT 1 FOR UPDATE',
            [$type, $series, $exceptId],
        );
        if ($branchId === false) {
            return null;
        }

        $name = $connection->fetchOne('SELECT descripcion FROM local WHERE local_id = ?', [$branchId]);

        return is_string($name) ? $name : 'esta empresa';
    }

    public static function duplicateMessage(string $series, ?string $branch): string
    {
        return sprintf(
            'La serie %s ya está registrada en %s para este tipo de comprobante. Usa otro código.',
            $series,
            $branch ?? 'esta empresa',
        );
    }

    /** Call under the corresponding type lock when provisioning a new series. */
    public static function firstAvailableCode(Connection $connection, string $type, string $prefix): ?string
    {
        $used = array_fill_keys(
            $connection->fetchFirstColumn('SELECT UPPER(TRIM(series)) FROM local_comprobante WHERE tipo_comprobante_id = ? FOR UPDATE', [
                $type,
            ]),
            value: true,
        );
        $digits = 4 - strlen($prefix);
        for ($number = 1; $number < (10 ** $digits); ++$number) {
            $candidate = $prefix . str_pad((string) $number, $digits, pad_string: '0');
            if (!array_key_exists($candidate, $used)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function ensureForBranch(
        Connection $connection,
        string $branch,
        string $type,
        string $prefix,
        ?string $associated = null,
    ): ?string {
        return self::withTypeLock($connection, $type, static function () use (
            $connection,
            $branch,
            $type,
            $prefix,
            $associated,
        ): ?string {
            $existing = $connection->fetchOne(
                'SELECT series FROM local_comprobante WHERE local_id = ? AND tipo_comprobante_id = ? AND serie_asociada <=> ? ORDER BY id LIMIT 1 FOR UPDATE',
                [$branch, $type, $associated],
            );
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }

            $series = self::firstAvailableCode($connection, $type, $prefix);
            if ($series !== null) {
                $connection->insert('local_comprobante', [
                    'local_id' => $branch,
                    'tipo_comprobante_id' => $type,
                    'series' => $series,
                    'serie_asociada' => $associated,
                    'is_active' => 1,
                ]);
            }

            return $series;
        });
    }
}
