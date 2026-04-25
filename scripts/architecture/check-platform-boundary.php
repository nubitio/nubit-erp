<?php

declare(strict_types=1);

/**
 * Static guard for the SPEC-004 Platform boundary.
 *
 * The checked paths target the current Platform candidates under src/Core and
 * the new Composer-ready src/Platform namespace. They should not import or
 * reference concrete efact app/domain namespaces while the code is prepared for
 * a future Composer package split.
 */

$checkedPaths = [
    'src/Platform',
    'src/Core/Context',
    'src/Core/Platform',
    'src/Core/Messenger',
    'src/Core/Logging',
    'src/Core/RateLimit',
    'src/Core/Cache',
    'src/Core/Filesystem',
    'src/Core/Http',
    'src/Core/Exception',
    'src/Core/Validation',
    'src/Core/Application/Command',
    'src/Core/EventListener',
];

$forbiddenNamespaces = [
    'App\\Entity\\',
    'App\\Repository\\',
    'App\\Dto\\',
    'App\\ControlPlane\\',
    'App\\EInvoicing\\',
    'App\\Inventory\\',
    'App\\Notification\\',
    'App\\State\\',
    'App\\Infrastructure\\',
];

$platformOnlyForbiddenNamespaces = [
    'App\\Core\\Util\\Constants',
    'App\\Core\\Security\\Auth\\',
    'App\\Core\\Symfony\\Security\\',
    'App\\Core\\Application\\Session\\Security',
    'App\\Core\\Doctrine\\',
    'App\\Efact\\Config\\',
    'App\\Efact\\Doctrine\\',
];

$arguments = array_slice($argv, 1);

if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
    fwrite(STDOUT, <<<HELP
Usage: php scripts/architecture/check-platform-boundary.php [--root=/path/to/project] [--self-test]

Fails when Platform candidate paths reference forbidden efact app/domain namespaces.

HELP);
    exit(0);
}

if (in_array('--self-test', $arguments, true)) {
    exit(runSelfTest($checkedPaths, $forbiddenNamespaces, $platformOnlyForbiddenNamespaces));
}

$root = getRoot($arguments);
$violations = array_merge(
    scanPlatformBoundary($root, $checkedPaths, $forbiddenNamespaces),
    scanPlatformBoundary($root, ['src/Platform'], $platformOnlyForbiddenNamespaces),
);

sortViolations($violations);

if ($violations !== []) {
    fwrite(STDERR, "Platform boundary violations found:\n");
    foreach ($violations as $violation) {
        fwrite(
            STDERR,
            sprintf(
                "- %s:%d references %s via %s\n",
                $violation['path'],
                $violation['line'],
                $violation['match'],
                $violation['pattern'],
            ),
        );
    }
    exit(1);
}

$scannedFileCount = countPhpFiles($root, $checkedPaths);
fwrite(STDOUT, sprintf("Platform boundary check passed (%d PHP files scanned).\n", $scannedFileCount));
exit(0);

/**
 * @param list<string> $arguments
 */
function getRoot(array $arguments): string
{
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--root=')) {
            $root = substr($argument, strlen('--root='));
            return rtrim($root, DIRECTORY_SEPARATOR);
        }
    }

    return getcwd() ?: dirname(__DIR__, 2);
}

/**
 * @param list<string> $checkedPaths
 * @param list<string> $forbiddenNamespaces
 * @return list<array{path: string, line: int, pattern: string, match: string}>
 */
function scanPlatformBoundary(string $root, array $checkedPaths, array $forbiddenNamespaces): array
{
    $violations = [];

    foreach (phpFiles($root, $checkedPaths) as $filePath) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Unable to read %s', $filePath));
        }

        foreach ($lines as $index => $line) {
            foreach ($forbiddenNamespaces as $forbiddenNamespace) {
                $pattern = '/' . preg_quote($forbiddenNamespace, '/') . '[A-Za-z0-9_\\\\]*/';
                if (preg_match($pattern, $line, $matches) !== 1) {
                    continue;
                }

                $violations[] = [
                    'path' => relativePath($root, $filePath),
                    'line' => $index + 1,
                    'pattern' => $forbiddenNamespace,
                    'match' => $matches[0],
                ];
            }
        }
    }

    usort(
        $violations,
        static fn (array $left, array $right): int => [$left['path'], $left['line'], $left['pattern']]
            <=> [$right['path'], $right['line'], $right['pattern']],
    );

    return $violations;
}

/**
 * @param list<array{path: string, line: int, pattern: string, match: string}> $violations
 */
function sortViolations(array &$violations): void
{
    usort(
        $violations,
        static fn (array $left, array $right): int => [$left['path'], $left['line'], $left['pattern']]
            <=> [$right['path'], $right['line'], $right['pattern']],
    );
}

/**
 * @param list<string> $checkedPaths
 * @return list<string>
 */
function phpFiles(string $root, array $checkedPaths): array
{
    $files = [];

    foreach ($checkedPaths as $checkedPath) {
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $checkedPath);
        if (!is_dir($absolutePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * @param list<string> $checkedPaths
 */
function countPhpFiles(string $root, array $checkedPaths): int
{
    return count(phpFiles($root, $checkedPaths));
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace(rtrim($root, DIRECTORY_SEPARATOR), '', $path), DIRECTORY_SEPARATOR);
}

/**
 * @param list<string> $checkedPaths
 * @param list<string> $forbiddenNamespaces
 * @param list<string> $platformOnlyForbiddenNamespaces
 */
function runSelfTest(array $checkedPaths, array $forbiddenNamespaces, array $platformOnlyForbiddenNamespaces): int
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'platform-boundary-guard-' . bin2hex(random_bytes(4));
    $checkedDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Context';
    $platformDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Platform';
    $controlPlaneAdapterDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ControlPlane' . DIRECTORY_SEPARATOR . 'Billing' . DIRECTORY_SEPARATOR . 'Quota';
    $efactDoctrineAdapterDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Efact' . DIRECTORY_SEPARATOR . 'Doctrine' . DIRECTORY_SEPARATOR . 'DBAL';
    $efactConfigDir = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Efact' . DIRECTORY_SEPARATOR . 'Config';

    mkdir($checkedDir, 0777, true);
    mkdir($platformDir, 0777, true);
    mkdir($controlPlaneAdapterDir, 0777, true);
    mkdir($efactDoctrineAdapterDir, 0777, true);
    mkdir($efactConfigDir, 0777, true);
    file_put_contents($checkedDir . DIRECTORY_SEPARATOR . 'Clean.php', "<?php\n\nnamespace App\\Core\\Context;\n\nfinal class Clean {}\n");
    file_put_contents($checkedDir . DIRECTORY_SEPARATOR . 'Violation.php', "<?php\n\nuse App\\Entity\\User;\n\nfinal class Violation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformEntityViolation.php', "<?php\n\nuse App\\Entity\\User;\n\nfinal class PlatformEntityViolation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformAuthViolation.php', "<?php\n\nuse App\\Core\\Security\\Auth\\JWTAuthenticator;\n\nfinal class PlatformAuthViolation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformConstantsViolation.php', "<?php\n\nuse App\\Core\\Util\\Constants;\n\nfinal class PlatformConstantsViolation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformCoreDoctrineViolation.php', "<?php\n\nuse App\\Core\\Doctrine\\DBAL\\TenantConnection;\n\nfinal class PlatformCoreDoctrineViolation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformEfactConfigViolation.php', "<?php\n\nuse App\\Efact\\Config\\EfactConstants;\n\nfinal class PlatformEfactConfigViolation {}\n");
    file_put_contents($platformDir . DIRECTORY_SEPARATOR . 'PlatformEfactDoctrineViolation.php', "<?php\n\nuse App\\Efact\\Doctrine\\DBAL\\TenantConnection;\n\nfinal class PlatformEfactDoctrineViolation {}\n");
    file_put_contents($controlPlaneAdapterDir . DIRECTORY_SEPARATOR . 'AllowedQuotaResolver.php', "<?php\n\nnamespace App\\ControlPlane\\Billing\\Quota;\n\nuse App\\Entity\\User;\nuse App\\Efact\\Config\\EfactConstants;\n\nfinal class AllowedQuotaResolver { public function supports(User \$user): string { return EfactConstants::TAX; } }\n");
    file_put_contents($efactDoctrineAdapterDir . DIRECTORY_SEPARATOR . 'AllowedTenantConnection.php', "<?php\n\nnamespace App\\Efact\\Doctrine\\DBAL;\n\nuse App\\Entity\\User;\n\nfinal class AllowedTenantConnection { public function userClass(): string { return User::class; } }\n");
    file_put_contents($efactConfigDir . DIRECTORY_SEPARATOR . 'AllowedEfactConstants.php', "<?php\n\nnamespace App\\Efact\\Config;\n\nabstract class AllowedEfactConstants { public const string TAX = 'app_option_igv'; }\n");

    try {
        $violations = scanPlatformBoundary($root, ['src/Core/Context'], $forbiddenNamespaces);
        $expected = $violations[0] ?? null;
        if (
            count($violations) !== 1
            || $expected['path'] !== 'src/Core/Context/Violation.php'
            || $expected['line'] !== 3
            || $expected['match'] !== 'App\\Entity\\User'
        ) {
            fwrite(STDERR, "Platform boundary guard self-test failed.\n");
            fwrite(STDERR, var_export($violations, true) . "\n");

            return 1;
        }

        $allScopeViolations = scanPlatformBoundary($root, $checkedPaths, $forbiddenNamespaces);
        $allScopeViolationMatches = array_column($allScopeViolations, 'match');
        if (
            count($allScopeViolations) !== 2
            || !in_array('App\\Entity\\User', $allScopeViolationMatches, true)
            || !selfTestHasViolation($allScopeViolations, 'src/Core/Context/Violation.php', 'App\\Entity\\User')
            || !selfTestHasViolation($allScopeViolations, 'src/Platform/PlatformEntityViolation.php', 'App\\Entity\\User')
            || selfTestHasPathPrefix($allScopeViolations, 'src/ControlPlane/')
            || selfTestHasPathPrefix($allScopeViolations, 'src/Efact/')
        ) {
            fwrite(STDERR, "Platform boundary guard scope self-test failed.\n");
            fwrite(STDERR, var_export($allScopeViolations, true) . "\n");

            return 1;
        }

        $platformViolations = scanPlatformBoundary($root, ['src/Platform'], $platformOnlyForbiddenNamespaces);
        $platformViolationMatches = array_column($platformViolations, 'match');
        if (
            count($platformViolations) !== 5
            || !in_array('App\\Core\\Security\\Auth\\JWTAuthenticator', $platformViolationMatches, true)
            || !in_array('App\\Core\\Util\\Constants', $platformViolationMatches, true)
            || !in_array('App\\Core\\Doctrine\\DBAL\\TenantConnection', $platformViolationMatches, true)
            || !in_array('App\\Efact\\Config\\EfactConstants', $platformViolationMatches, true)
            || !in_array('App\\Efact\\Doctrine\\DBAL\\TenantConnection', $platformViolationMatches, true)
        ) {
            fwrite(STDERR, "Platform-only boundary guard self-test failed.\n");
            fwrite(STDERR, var_export($platformViolations, true) . "\n");

            return 1;
        }

        fwrite(STDOUT, "Platform boundary guard self-test passed.\n");

        return 0;
    } finally {
        removeDirectory($root);
    }
}

/**
 * @param list<array{path: string, line: int, pattern: string, match: string}> $violations
 */
function selfTestHasViolation(array $violations, string $path, string $match): bool
{
    foreach ($violations as $violation) {
        if ($violation['path'] === $path && $violation['match'] === $match) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array{path: string, line: int, pattern: string, match: string}> $violations
 */
function selfTestHasPathPrefix(array $violations, string $pathPrefix): bool
{
    foreach ($violations as $violation) {
        if (str_starts_with($violation['path'], $pathPrefix)) {
            return true;
        }
    }

    return false;
}

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
            continue;
        }

        unlink($file->getPathname());
    }

    rmdir($path);
}
