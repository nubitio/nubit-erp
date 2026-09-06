<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function file_get_contents;

/**
 * NB-10 direction rules for the first vertical (AR-002 / AR-007):
 * nothing in the ERP core or elsewhere may depend on App\Pharmacy, and the
 * shared Catalog product entity must stay free of pharmacy-specific fields.
 */
final class PharmacyBoundaryTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../src';

    public function testNoContextOutsidePharmacyImportsIt(): void
    {
        $offenders = [];
        foreach ($this->phpFiles(self::SRC) as $file) {
            if (str_contains($file, '/src/Pharmacy/')) {
                continue;
            }
            if (preg_match('/\bApp\\\\Pharmacy\\\\/', (string) file_get_contents($file)) === 1) {
                $offenders[] = substr($file, (int) strpos($file, '/src/') + 1);
            }
        }

        sort($offenders);
        self::assertSame(
            [],
            $offenders,
            "ERP/core code must not depend on the Pharmacy vertical:\n  " . implode("\n  ", $offenders),
        );
    }

    public function testCatalogProductHasNoPharmacyFields(): void
    {
        $product = (string) file_get_contents(self::SRC . '/Catalog/Entity/Product.php');

        foreach (['prescription', 'activeIngredient', 'sanitaryRegistration', 'Pharmacy', 'batch'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase(
                $needle,
                $product,
                sprintf('Catalog\\Product must not carry pharmacy concept "%s" (AR-007).', $needle),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME,
        ));
        foreach ($iterator as $path) {
            if (\is_string($path) && str_ends_with($path, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
