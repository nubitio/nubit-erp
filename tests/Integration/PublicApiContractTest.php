<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('keeps the versioned public contract complete and explicit', function (): void {
    $contract = Yaml::parseFile(__DIR__.'/../../docs/api/public-v1.yaml');

    expect($contract['openapi'])->toBe('3.1.0')
        ->and($contract['info']['version'])->toBe('1.0.0')
        ->and($contract['components']['securitySchemes']['ApiKey']['type'])->toBe('http')
        ->and($contract['paths'])->toHaveKeys([
            '/api/v1/ping',
            '/api/v1/documents',
            '/api/v1/documents/{reference}',
            '/api/v1/webhook-endpoints',
            '/api/v1/webhook-endpoints/{id}',
            '/api/v1/webhook-endpoints/{id}/rotate-secret',
        ]);
});
