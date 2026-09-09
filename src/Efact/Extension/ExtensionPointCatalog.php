<?php

declare(strict_types=1);

namespace App\Efact\Extension;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

use function is_string;

/**
 * NB-16 (#342): the registry of core extension points. Discovers every
 * interface under src/ marked with {@see AsExtensionPoint} so verticals — and
 * ExtensionPointCatalogTest, and the docs — have one list to work from.
 */
final class ExtensionPointCatalog
{
    private const string SRC = __DIR__ . '/../..';

    /**
     * @return list<array{
     *     id: string,
     *     interface: class-string,
     *     summary: string,
     *     default: string,
     *     resolution: string,
     * }>
     */
    public function all(): array
    {
        $points = [];
        foreach ($this->interfaceNames() as $interface) {
            $attributes = new ReflectionClass($interface)->getAttributes(AsExtensionPoint::class);
            if ($attributes === []) {
                continue;
            }

            $point = $attributes[0]->newInstance();
            $points[] = [
                'id' => $point->id,
                'interface' => $interface,
                'summary' => $point->summary,
                'default' => $point->default,
                'resolution' => $point->resolution,
            ];
        }

        usort($points, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);

        return $points;
    }

    /**
     * Every `App\…Interface` declared under src/, derived from the PSR-4 path.
     *
     * @return list<class-string>
     */
    private function interfaceNames(): array
    {
        $root = realpath(self::SRC);
        if ($root === false) {
            return [];
        }

        $names = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME,
        ));

        foreach ($files as $file) {
            if (!is_string($file) || !str_ends_with($file, 'Interface.php')) {
                continue;
            }

            $relative = trim(str_replace($root, replace: '', subject: $file), DIRECTORY_SEPARATOR);
            $fqcn = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $relative);
            if (interface_exists($fqcn)) {
                $names[] = $fqcn;
            }
        }

        sort($names);

        return $names;
    }
}
