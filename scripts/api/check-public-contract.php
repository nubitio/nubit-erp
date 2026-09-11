<?php

declare(strict_types=1);

use App\Integration\Api\PublicContractValidator;
use App\Kernel;
use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$contract = Yaml::parseFile($root.'/docs/api/public-v1.yaml');
$kernel = new Kernel('test', false);
$kernel->boot();
$errors = (new PublicContractValidator())->validate(
    $contract,
    $kernel->getContainer()->get('router')->getRouteCollection(),
);
if ($errors !== []) {
    fwrite(STDERR, "Public API contract validation failed:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

$operationCount = array_sum(array_map(
    static fn(array $path): int => count(array_intersect(array_keys($path), ['get', 'post', 'put', 'patch', 'delete'])),
    $contract['paths'],
));
fwrite(STDOUT, sprintf("Public API contract OK: %d paths, %d operations.\n", count($contract['paths']), $operationCount));
