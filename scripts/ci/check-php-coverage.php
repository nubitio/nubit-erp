#!/usr/bin/env php
<?php

declare(strict_types=1);

$argc = isset($_SERVER['argc']) ? (int) $_SERVER['argc'] : 0;
/** @var array<int, string> $argv */
$argv = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : [];

if ($argc < 3) {
    fwrite(STDERR, "Usage: check-php-coverage.php <clover.xml> <min-percent>\n");
    exit(1);
}

$cloverFile = $argv[1];
$minimum = (float) $argv[2];

if (!is_file($cloverFile)) {
    fwrite(STDERR, "Coverage file not found: {$cloverFile}\n");
    exit(1);
}

$xml = simplexml_load_file($cloverFile);
if (!$xml instanceof \SimpleXMLElement) {
    fwrite(STDERR, "Unable to parse Clover XML: {$cloverFile}\n");
    exit(1);
}

$metrics = $xml->project?->metrics;
if (!$metrics instanceof \SimpleXMLElement) {
    fwrite(STDERR, "Missing project metrics in Clover file: {$cloverFile}\n");
    exit(1);
}

$statements = (int) ($metrics['statements'] ?? 0);
$coveredStatements = (int) ($metrics['coveredstatements'] ?? 0);

if ($statements <= 0) {
    fwrite(STDERR, "No statements found in coverage report.\n");
    exit(1);
}

$coverage = ($coveredStatements / $statements) * 100;
$formattedCoverage = number_format($coverage, 2);
$formattedMinimum = number_format($minimum, 2);

fwrite(STDOUT, "PHP line coverage: {$formattedCoverage}% (minimum {$formattedMinimum}%)\n");

if ($coverage + 0.00001 < $minimum) {
    fwrite(STDERR, "Coverage gate failed.\n");
    exit(1);
}

// SLICE-TEST-001: raised to 70% for EInvoicing + Sale
/** @var array<string, float> $directoryThresholds */
$directoryThresholds = [
    'src/EInvoicing/' => 70.0,
    'src/State/Sale/' => 70.0,
    // #314: highest-risk module financially (cash ledger); was 68.32% before
    // adding CashRegisterReportControllerTest (0/76 statements covered).
    // Set below the current ~79% to leave headroom, not at the ceiling.
    'src/CashRegister/' => 75.0,
];

$directoryFailed = false;

foreach ($directoryThresholds as $dirPrefix => $dirMinimum) {
    $dirStatements = 0;
    $dirCovered = 0;

    foreach ($xml->xpath('//file') ?: [] as $fileNode) {
        $filePath = (string) ($fileNode['name'] ?? '');
        // Match if the path contains the expected directory segment
        if (str_contains($filePath, str_replace('/', DIRECTORY_SEPARATOR, $dirPrefix))
            || str_contains($filePath, $dirPrefix)
        ) {
            $fileMetrics = $fileNode->metrics;
            if ($fileMetrics instanceof \SimpleXMLElement) {
                $dirStatements += (int) ($fileMetrics['statements'] ?? 0);
                $dirCovered   += (int) ($fileMetrics['coveredstatements'] ?? 0);
            }
        }
    }

    if ($dirStatements <= 0) {
        fwrite(STDOUT, "  [{$dirPrefix}] No statements found — skipping directory gate.\n");
        continue;
    }

    $dirCoverage = ($dirCovered / $dirStatements) * 100;
    $formattedDirCoverage = number_format($dirCoverage, 2);
    $formattedDirMinimum  = number_format($dirMinimum, 2);

    fwrite(STDOUT, "  [{$dirPrefix}] coverage: {$formattedDirCoverage}% (minimum {$formattedDirMinimum}%)\n");

    if ($dirCoverage + 0.00001 < $dirMinimum) {
        fwrite(STDERR, "  Coverage gate failed for directory: {$dirPrefix}\n");
        $directoryFailed = true;
    }
}

if ($directoryFailed) {
    exit(1);
}

exit(0);
