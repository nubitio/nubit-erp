<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Efact\Extension\AsExtensionPoint;
use App\Efact\Extension\ExtensionPointCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function sprintf;

/**
 * NB-16 (#342), gap G8: every core seam a vertical may specialise is an
 * interface marked with #[AsExtensionPoint], its declared `default` really
 * implements it, and the catalog matches the documentation. Adding a vertical
 * rule is then "register an implementation" — never a core edit.
 */
final class ExtensionPointCatalogTest extends TestCase
{
    private const string DOC = __DIR__ . '/../../docs/architecture/extension-points.md';

    public function testAtLeastOneExtensionPointIsRegistered(): void
    {
        static::assertNotEmpty(
            new ExtensionPointCatalog()->all(),
            'no #[AsExtensionPoint] interfaces found — the discovery scan is broken',
        );
    }

    /**
     * @param array{id: string, interface: string, summary: string, default: string, resolution: string} $point
     */
    #[DataProvider('extensionPoints')]
    public function testDefaultImplementationSatisfiesTheContract(array $point): void
    {
        static::assertTrue(
            interface_exists($point['interface']),
            sprintf('%s: interface %s does not exist', $point['id'], $point['interface']),
        );
        static::assertTrue(
            class_exists($point['default']),
            sprintf('%s: default %s does not exist', $point['id'], $point['default']),
        );
        static::assertTrue(
            new ReflectionClass($point['default'])->implementsInterface($point['interface']),
            sprintf('%s: default %s does not implement %s', $point['id'], $point['default'], $point['interface']),
        );
        static::assertContains(
            $point['resolution'],
            [
                AsExtensionPoint::RESOLUTION_BINDING,
                AsExtensionPoint::RESOLUTION_AUTOWIRE,
                AsExtensionPoint::RESOLUTION_RESOLVER,
            ],
            sprintf('%s: unknown resolution "%s"', $point['id'], $point['resolution']),
        );
    }

    public function testEveryExtensionPointIdIsUnique(): void
    {
        $ids = array_column(new ExtensionPointCatalog()->all(), 'id');

        static::assertSame(array_values(array_unique($ids)), $ids, 'duplicate extension-point id');
    }

    public function testDocumentationListsEveryRegisteredExtensionPoint(): void
    {
        $doc = (string) file_get_contents(self::DOC);
        $missing = [];
        foreach (new ExtensionPointCatalog()->all() as $point) {
            if (!str_contains($doc, $point['id'])) {
                $missing[] = $point['id'];
            }
        }

        static::assertSame(
            [],
            $missing,
            'docs/architecture/extension-points.md is missing: ' . implode(', ', $missing),
        );
    }

    /**
     * @return iterable<string, array{array{id: string, interface: string, summary: string, default: string, resolution: string}}>
     */
    public static function extensionPoints(): iterable
    {
        foreach (new ExtensionPointCatalog()->all() as $point) {
            yield $point['id'] => [$point];
        }
    }
}
