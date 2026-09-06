<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

/**
 * architecture/contexts.yaml (NB-2) structural invariants — no source scanning.
 * phparkitect.php builds its layer map from this file, so gaps surface here first.
 */
final class ContextManifestStructureTest extends TestCase
{
    public function testEntriesMatchTheSourceTree(): void
    {
        $manifest = new ContextManifest();
        $dirs = $manifest->sourceDirectories();
        $entries = array_keys($manifest->contexts);
        sort($dirs);
        sort($entries);

        static::assertSame(
            $dirs,
            $entries,
            sprintf(
                "contexts.yaml out of sync with src/.\nonly in src/: %s\nonly in manifest: %s",
                implode(', ', array_diff($dirs, $entries)),
                implode(', ', array_diff($entries, $dirs)),
            ),
        );
    }

    public function testEveryLayerIsDeclared(): void
    {
        $manifest = new ContextManifest();
        $unknown = [];

        foreach ($manifest->contexts as $name => $context) {
            if (!in_array($context['layer'], $manifest->layers, strict: true)) {
                $unknown[] = sprintf('%s (%s)', $name, $context['layer']);
            }
        }

        static::assertSame([], $unknown, 'contexts.yaml uses undeclared layers: ' . implode(', ', $unknown));
    }

    public function testDependsOnTargetsExistAndAreNotSelfReferential(): void
    {
        $manifest = new ContextManifest();
        $names = array_keys($manifest->contexts);
        $problems = [];

        foreach ($manifest->contexts as $name => $context) {
            foreach ($context['dependsOn'] ?? [] as $target) {
                if ($target === $name || !in_array($target, $names, strict: true)) {
                    $problems[] = sprintf('%s -> %s', $name, $target);
                }
            }
        }

        static::assertSame(
            [],
            $problems,
            'contexts.yaml dependsOn has self-references or unknown targets: ' . implode(', ', $problems),
        );
    }
}
