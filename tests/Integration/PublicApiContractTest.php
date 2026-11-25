<?php

declare(strict_types=1);

use App\Integration\Api\PublicContractValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

uses(KernelTestCase::class);

final class PublicApiContractTestKernel extends KernelTestCase
{
    public static function routeCollection(): RouteCollection
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router->getRouteCollection();
    }

    public static function handleRequest(Request $request): Response
    {
        return self::bootKernel()->handle($request);
    }

}

it('keeps the versioned public contract complete and explicit', function (): void {
    $contract = Yaml::parseFile(__DIR__.'/../../docs/api/public-v1.yaml');

    expect($contract['openapi'])->toBe('3.1.0')
        ->and($contract['info']['version'])->toBe('1.0.0')
        ->and($contract['components']['securitySchemes']['ApiKey']['type'])->toBe('http')
        ->and($contract['components']['securitySchemes']['ApiKey']['scheme'])->toBe('bearer')
        ->and($contract['components']['securitySchemes']['TenantJwt']['scheme'])->toBe('bearer')
        ->and($contract['components']['securitySchemes']['ApiKey']['x-scopes'])
        ->toContain('documents:read', 'documents:write', 'webhooks:manage', 'sales:read', 'sales:write', 'payments:write')
        ->and($contract['components']['schemas'])->toHaveKeys([
            'Auth',
            'Status',
            'Pagination',
            'Error',
        ])
        ->and($contract['paths'])->toHaveKeys([
            '/api/v1/ping',
            '/api/v1/sales',
            '/api/v1/sales/{id}',
            '/api/v1/sales/{id}/payments',
            '/api/v1/documents',
            '/api/v1/documents/{reference}',
            '/api/v1/webhook-endpoints',
            '/api/v1/webhook-endpoints/{id}',
            '/api/v1/webhook-endpoints/{id}/rotate-secret',
            '/api/v1/onboarding/spreadsheets/template/{type}',
            '/api/v1/onboarding/spreadsheets/rows',
            '/api/v1/onboarding/receivables',
            '/api/v1/bank-statement-imports',
            '/api/v1/bank-reconciliation/unreconciled-movements',
            '/api/v1/ple/preview',
            '/api/v1/ple/validate',
            '/api/v1/ple/zip',
            '/api/v1/ple/sales',
            '/api/v1/ple/purchases',
            '/api/v1/ple/diary',
            '/api/v1/ple/ledger',
            '/api/v1/sire/rvie',
            '/api/v1/sire/rvie/preview',
            '/api/v1/sire/rce',
            '/api/v1/sire/rce/preview',
            '/api/v1/payroll/{payrollRunId}/plame',
            '/api/v1/exchange-rate',
            '/api/v1/exchange-rate/sync',
            '/api/v1/products/export',
            '/api/v1/products/import/template',
            '/api/v1/products/import',
            '/api/v1/credit-notes/reasons',
        ]);

    $errors = (new PublicContractValidator())->validate(
        $contract,
        PublicApiContractTestKernel::routeCollection(),
    );

    expect($errors)->toBe([]);
});

it('authenticates commercial requests through the API key firewall', function (string $path): void {
    $response = PublicApiContractTestKernel::handleRequest(
        Request::create(
            'https://integration-gateway.example.test' . $path,
            'POST',
            server: ['HTTP_AUTHORIZATION' => 'Bearer nb_malformed'],
        ),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode((string) $response->getContent(), associative: true))
        ->toBe(['error' => ['code' => 'unauthenticated', 'message' => 'Malformed API key.']]);
})->with([
    'sale creation' => ['/api/v1/sales'],
    'payment registration' => ['/api/v1/sales/018f0d0a-e38f-7621-af46-000000000001/payments'],
]);
