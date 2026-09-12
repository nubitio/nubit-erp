<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use function is_array;
use function is_string;

/**
 * Read/write access to the committed snapshot of every declared public
 * contract's method surface (tests/Architecture/fixtures/public-contracts.json).
 * Owned by PublicContractSnapshotTest (NB-21).
 */
final class PublicContractSnapshot
{
    private const string FILE = __DIR__ . '/fixtures/public-contracts.json';

    public static function path(): string
    {
        return self::FILE;
    }

    public static function exists(): bool
    {
        return is_file(self::FILE);
    }

    /**
     * @return array<string, list<string>> fqcn => signature strings
     */
    public static function read(): array
    {
        if (!self::exists()) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents(self::FILE), associative: true);

        /** @var array<string, array<int, string>> $entries */
        $entries = is_array($decoded) ? $decoded : [];
        $snapshot = [];
        foreach ($entries as $fqcn => $signatures) {
            $snapshot[$fqcn] = array_values(array_map(strval(...), $signatures));
        }

        return $snapshot;
    }

    /**
     * Every FQCN listed under any context's `publicContracts`, in manifest order.
     *
     * @return list<string>
     */
    public static function declared(ContextManifest $manifest): array
    {
        $declared = [];
        foreach ($manifest->contexts as $entry) {
            foreach ($entry['publicContracts'] ?? [] as $fqcn) {
                $declared[] = $fqcn;
            }
        }

        return $declared;
    }

    /**
     * Rebuild the file from the interfaces currently on disk. Only runs when
     * UPDATE_CONTRACT_SNAPSHOT is set to a non-empty, non-"0" value.
     */
    public static function regenerateIfRequested(ContextManifest $manifest): void
    {
        $flag = getenv('UPDATE_CONTRACT_SNAPSHOT');
        if (!is_string($flag) || $flag === '' || $flag === '0') {
            return;
        }

        $out = [];
        foreach (self::declared($manifest) as $fqcn) {
            if (!interface_exists($fqcn)) {
                continue;
            }

            $out[$fqcn] = InterfaceSignature::describe($fqcn);
        }

        ksort($out);
        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            mkdir($dir, permissions: 0o775, recursive: true);
        }

        $json = json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents(self::FILE, $json . "\n");
    }
}
