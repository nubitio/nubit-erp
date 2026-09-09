<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

use function assert;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;

/**
 * Reader for architecture/contexts.yaml (NB-2), plus the source scans the
 * manifest tests cross-check it against.
 *
 * @phpstan-type ContextEntry array{
 *     namespace: string,
 *     layer: string,
 *     dependsOn?: list<string>,
 *     publicContracts?: list<string>,
 *     ownsTables?: list<string>,
 *     retired?: bool,
 * }
 */
final class ContextManifest
{
    private const string FILE = __DIR__ . '/../../architecture/contexts.yaml';
    public const string SRC = __DIR__ . '/../../src';

    /** @var list<string> */
    public array $layers;

    /** @var array<string, ContextEntry> */
    public array $contexts;

    /**
     * Tenant-DB tables created by our migrations but owned by a vendor package
     * or the framework, not by any src/ context: table name => owning package.
     *
     * @var array<string, string>
     */
    public array $externalTables;

    public function __construct()
    {
        $data = Yaml::parseFile(self::FILE);
        assert(is_array($data), 'contexts.yaml must parse to a mapping');

        /**
         * @var array{
         *     layers: list<string>,
         *     contexts: array<string, ContextEntry>,
         *     externalTables?: array<string, string>,
         * } $data
         */
        $this->layers = $data['layers'];
        $this->contexts = $data['contexts'];
        $this->externalTables = $data['externalTables'] ?? [];
    }

    /**
     * Directory names directly under src/.
     *
     * @return list<string>
     */
    public function sourceDirectories(): array
    {
        $scan = scandir(self::SRC);
        if ($scan === false) {
            return [];
        }

        $dirs = [];
        foreach ($scan as $entry) {
            if (!($entry !== '.' && $entry !== '..' && is_dir(self::SRC . '/' . $entry))) {
                continue;
            }

            $dirs[] = $entry;
        }

        return $dirs;
    }

    /**
     * Other context names whose namespace the given context references in code.
     *
     * @return list<string>
     */
    public function crossReferences(string $context): array
    {
        $names = array_keys($this->contexts);
        $found = [];
        foreach ($this->scan($context, '/\bApp\\\\([A-Z][A-Za-z]+)\\\\/') as $hit) {
            if (!($hit !== $context && in_array($hit, $names, strict: true))) {
                continue;
            }

            $found[$hit] = true;
        }

        return array_keys($found);
    }

    /**
     * Table names declared via `#[ORM\Table(name: '...')]` on entities in the context.
     *
     * @return list<string>
     */
    public function entityTables(string $context): array
    {
        return array_values(array_unique($this->scan($context, '/#\[ORM\\\\Table\(name:\s*[\'"]([a-z0-9_]+)[\'"]/')));
    }

    /**
     * Lowercased, unique table names that appear as the target of a raw-SQL
     * write (INSERT / UPDATE / DELETE / REPLACE / TRUNCATE) anywhere in the
     * context's .php files. Reads (FROM / JOIN) are deliberately not matched:
     * NB-15 guards a context *mutating* another context's tables, not
     * denormalised read models.
     *
     * @return list<string>
     */
    public function rawSqlWriteTargets(string $context): array
    {
        $pattern =
            '/\b(?:INSERT\s+(?:IGNORE\s+)?INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|TRUNCATE(?:\s+TABLE)?)'
            . '\s+`?([a-z_][a-z0-9_]{2,})`?/i';

        return array_values(array_unique(array_map(
            static fn(string $table): string => strtolower($table),
            $this->scan($context, $pattern),
        )));
    }

    /**
     * Short names of interfaces declared anywhere in the context.
     *
     * @return list<string>
     */
    public function declaredInterfaces(string $context): array
    {
        return $this->scan($context, '/\binterface\s+([A-Za-z_]+)\b/');
    }

    /**
     * Capture-group-1 matches of $pattern across every .php file under src/<context>.
     *
     * @return list<string>
     */
    private function scan(string $context, string $pattern): array
    {
        $dir = self::SRC . '/' . $context;
        if (!is_dir($dir)) {
            return [];
        }

        $hits = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME,
        ));

        foreach ($files as $file) {
            assert(is_string($file), 'iterator yields pathnames');
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $matches = [];
            preg_match_all($pattern, (string) file_get_contents($file), $matches);
            $hits = [...$hits, ...$matches[1]];
        }

        return $hits;
    }
}
