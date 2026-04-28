<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Enforces that bounded contexts do not import from downstream or peer contexts
 * that would create unwanted coupling.
 *
 * Dependency rules (allowed → allowed to import):
 *   Catalog      → Core, Dto, Efact, Inventory*, Media, Organization, Validator
 *   Contacts     → Core, Dto, Efact, Inventory*, Validator
 *   Inventory    → Catalog, Core, Dto, Efact, Organization, Sales*, State
 *   Purchasing   → Catalog, Contacts, Core, Dto, Efact, Identity, Inventory, Organization, State
 *   CashRegister → Core, Dto, Efact, Enum, Identity, Organization, State
 *   Sales        → Catalog, Contacts, Core, Dto, Efact, Inventory, Organization, State
 *   Billing      → AI, Catalog, Contacts, Core, Dto, Efact, EInvoicing, Organization, Sale*, Sales, State
 *   Platform     → (no App\ imports at all)
 *
 * *Inventory→Sales and Catalog→Inventory are soft deps tracked here as warnings;
 *  the hard rules below guard against the most harmful coupling directions.
 */
final class ContextBoundaryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Platform: must never import App\
    // -------------------------------------------------------------------------

    public function testPlatformDoesNotImportAppNamespace(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Platform',
            '/^use App\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Catalog: must not import Sales, Billing, Purchasing, CashRegister
    // -------------------------------------------------------------------------

    public function testCatalogDoesNotImportDownstreamContexts(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Catalog',
            '/^use App\\\\(?:Sales|Billing|Purchasing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Contacts: must not import Sales, Billing, Purchasing, CashRegister
    // -------------------------------------------------------------------------

    public function testContactsDoesNotImportDownstreamContexts(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Contacts',
            '/^use App\\\\(?:Sales|Billing|Purchasing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Inventory: must not import Billing, Purchasing, CashRegister
    // -------------------------------------------------------------------------

    public function testInventoryDoesNotImportBillingOrPurchasingOrCashRegister(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Inventory',
            '/^use App\\\\(?:Billing|Purchasing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Purchasing: must not import Sales, Billing, CashRegister
    // -------------------------------------------------------------------------

    public function testPurchasingDoesNotImportSalesOrBillingOrCashRegister(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Purchasing',
            '/^use App\\\\(?:Sales|Billing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // CashRegister: must not import Sales, Billing, Purchasing, Catalog, Contacts
    // -------------------------------------------------------------------------

    public function testCashRegisterDoesNotImportPeerDomainContexts(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/CashRegister',
            '/^use App\\\\(?:Sales|Billing|Purchasing|Catalog|Contacts)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Sales: must not import Billing, Purchasing, CashRegister
    // -------------------------------------------------------------------------

    public function testSalesDoesNotImportBillingOrPurchasingOrCashRegister(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Sales',
            '/^use App\\\\(?:Billing|Purchasing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Reporting: must not import Sales, Billing, Purchasing, CashRegister
    // -------------------------------------------------------------------------

    public function testReportingDoesNotImportTransactionalContexts(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Reporting',
            '/^use App\\\\(?:Sales|Billing|Purchasing|CashRegister)\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    // -------------------------------------------------------------------------
    // Helpers (same pattern as ControlPlaneBoundaryTest)
    // -------------------------------------------------------------------------

    /**
     * @param list<string> $allowedPathFragments
     * @return list<string>
     */
    private function findUseStatementViolations(
        string $directory,
        string $pattern,
        array $allowedPathFragments = [],
    ): array {
        if (!is_dir($directory)) {
            return [];
        }

        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

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
        foreach ($allowedPathFragments as $fragment) {
            if (str_contains($path, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $path): string
    {
        $root = realpath(__DIR__ . '/../..');
        if ($root === false) {
            return $path;
        }

        return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    }

    /**
     * @param list<string> $violations
     */
    private function formatViolations(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        return "Boundary violations found:\n- " . implode("\n- ", $violations);
    }
}
