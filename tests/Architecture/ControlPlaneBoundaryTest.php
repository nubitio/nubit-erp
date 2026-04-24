<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ControlPlaneBoundaryTest extends TestCase
{
    public function testCoreDoesNotImportConcreteControlPlaneClasses(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/Core',
            '/^use App\\\\ControlPlane\\\\/m',
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    public function testControlPlaneExternalModuleImportsStayInAdapters(): void
    {
        $violations = $this->findUseStatementViolations(
            __DIR__ . '/../../src/ControlPlane',
            '/^use App\\\\(?:Setup|EInvoicing|Dto\\\\Setup)\\\\/m',
            [DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR],
        );

        self::assertSame([], $violations, $this->formatViolations($violations));
    }

    /**
     * @param list<string> $allowedPathFragments
     * @return list<string>
     */
    private function findUseStatementViolations(
        string $directory,
        string $pattern,
        array $allowedPathFragments = [],
    ): array {
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
