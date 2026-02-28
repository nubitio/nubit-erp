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

exit(0);
