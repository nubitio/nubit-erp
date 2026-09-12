<?php

declare(strict_types=1);

namespace App\Integration\Api;

use Symfony\Component\Routing\RouteCollection;

final class PublicContractValidator
{
    /**
     * @param array<string, mixed> $contract
     * @return list<string>
     */
    public function validate(array $contract, RouteCollection $routes): array
    {
        return array_merge(
            new OpenApiContractSemantics()->validate($contract),
            new RouteContractCoverage()->validate($contract, $routes),
        );
    }
}
