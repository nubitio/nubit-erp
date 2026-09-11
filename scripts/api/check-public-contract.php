<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$contractPath = $root.'/docs/api/public-v1.yaml';
$contract = Yaml::parseFile($contractPath);

if (($contract['openapi'] ?? null) !== '3.1.0' || ($contract['info']['version'] ?? null) !== '1.0.0') {
    fwrite(STDERR, "Public contract must be OpenAPI 3.1.0 version 1.0.0.\n");
    exit(1);
}

$documented = [];
foreach ($contract['paths'] ?? [] as $path => $operations) {
    foreach (array_keys($operations) as $method) {
        if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
            $documented[] = strtoupper($method).' '.$path;
        }
    }
}

$implemented = [];
foreach (glob($root.'/src/Integration/Controller/Api/V1/*.php') ?: [] as $file) {
    $source = (string) file_get_contents($file);
    preg_match_all('/#\[Route\(\s*[\'\"]([^\'\"]+)[\'\"][\s\S]*?methods:\s*\[([^\]]+)\]/', $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        preg_match_all('/[\'\"](GET|POST|PUT|PATCH|DELETE)[\'\"]/', $match[2], $methods);
        foreach ($methods[1] ?? [] as $method) {
            $implemented[] = $method.' '.$match[1];
        }
    }
}

sort($documented);
sort($implemented);
if ($documented !== $implemented) {
    fwrite(STDERR, "Public API route drift detected.\nDocumented: ".implode(', ', $documented)."\nImplemented: ".implode(', ', $implemented)."\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Public API contract OK: %d routes, %d operations.\n", count(array_unique($implemented)), count($implemented)));
