<?php

declare(strict_types=1);

use App\Integration\Api\PublicContractValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

uses(KernelTestCase::class);

it('keeps the versioned public contract complete and explicit', function (): void {
    $contract = Yaml::parseFile(__DIR__.'/../../docs/api/public-v1.yaml');

    expect($contract['openapi'])->toBe('3.1.0')
        ->and($contract['info']['version'])->toBe('1.0.0')
        ->and($contract['components']['securitySchemes']['ApiKey']['type'])->toBe('http')
        ->and($contract['components']['securitySchemes']['ApiKey']['scheme'])->toBe('bearer')
        ->and($contract['components']['securitySchemes']['TenantJwt']['scheme'])->toBe('bearer')
        ->and($contract['components']['securitySchemes']['ApiKey']['x-scopes'])
        ->toContain('documents:read', 'documents:write', 'webhooks:manage');

    $errors = (new PublicContractValidator())->validate(
        $contract,
        static::getContainer()->get('router')->getRouteCollection(),
    );

    expect($errors)->toBe([]);
});
