<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class LedgerFiscalImmutabilityTest extends TestCase
{
    public function testCashMovementResourceDoesNotExposeGenericPatchOrDelete(): void
    {
        $content = $this->readProjectFile('src/CashRegister/Entity/CashMovement.php');

        static::assertDoesNotMatchRegularExpression('/use ApiPlatform\\\\Metadata\\\\(?:Patch|Delete);/', $content);
        static::assertDoesNotMatchRegularExpression('/new (?:Patch|Delete)\s*\(/', $content);
    }

    public function testTaxDocumentResourceDoesNotExposeGenericPatchOrDelete(): void
    {
        $content = $this->readProjectFile('src/Billing/Entity/TaxDocument.php');

        static::assertDoesNotMatchRegularExpression('/use ApiPlatform\\\\Metadata\\\\(?:Patch|Delete);/', $content);
        static::assertDoesNotMatchRegularExpression('/new (?:Patch|Delete)\s*\(/', $content);
    }

    public function testApplicationCodeDoesNotMutateCashMovementRows(): void
    {
        $violations = $this->findContentViolations(
            __DIR__ . '/../../src',
            '/\b(?:UPDATE|DELETE\s+FROM)\s+cash_movements\b/i',
            [
                // E2E reset/seed code may clean test fixtures; production paths must not.
                'src/Setup/',
            ],
        );

        static::assertSame([], $violations, $this->formatViolations($violations));
    }

    public function testApplicationCodeDoesNotMutateTreasuryMovementRows(): void
    {
        $violations = $this->findContentViolations(
            __DIR__ . '/../../src',
            '/\b(?:UPDATE|DELETE\s+FROM)\s+treasury_movement\b|->(?:update|delete)\(\s*[\'"]treasury_movement[\'"]/i',
            [
                'src/Setup/',
            ],
        );

        static::assertSame([], $violations, $this->formatViolations($violations));
    }

    public function testApplicationCodeDoesNotUpsertCashMovementRows(): void
    {
        $violations = $this->findContentViolations(
            __DIR__ . '/../../src',
            '/cash_movements[\s\S]{0,1500}ON\s+DUPLICATE\s+KEY\s+UPDATE/i',
            [
                'src/Setup/',
            ],
        );

        static::assertSame([], $violations, $this->formatViolations($violations));
    }

    private function readProjectFile(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        $content = file_get_contents($path);
        static::assertIsString($content, sprintf('Unable to read %s', $relativePath));

        return $content;
    }

    /**
     * @param list<string> $allowedPathFragments
     * @return list<string>
     */
    private function findContentViolations(string $directory, string $pattern, array $allowedPathFragments = []): array
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $directory,
            \FilesystemIterator::SKIP_DOTS,
        ));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if ($this->isAllowedPath($path, $allowedPathFragments)) {
                continue;
            }

            $content = (string) file_get_contents($path);
            if (preg_match($pattern, $content) === 1) {
                $violations[] = $this->relativePath($path);
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param list<string> $allowedPathFragments
     */
    private function isAllowedPath(string $path, array $allowedPathFragments): bool
    {
        $normalized = str_replace(search: '\\', replace: '/', subject: $this->relativePath($path));
        foreach ($allowedPathFragments as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        return ltrim(
            string: str_replace(search: dirname(path: __DIR__, levels: 2), replace: '', subject: $path),
            characters: DIRECTORY_SEPARATOR,
        );
    }

    /**
     * @param list<string> $violations
     */
    private function formatViolations(array $violations): string
    {
        return $violations === [] ? '' : "Immutable ledger/fiscal violations:\n- " . implode("\n- ", $violations);
    }
}
