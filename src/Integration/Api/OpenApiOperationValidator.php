<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiOperationValidator
{
    /**
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $schemes
     * @param array<string, mixed> $operation
     * @return list<string>
     */
    public function validate(array $contract, array $schemes, string $path, string $method, array $operation): array
    {
        $key = strtoupper($method) . ' ' . $path;

        return array_merge(
            new OpenApiBasicValidator()->validate($key, $operation),
            new OpenApiParametersValidator()->validate($key, $path, $operation),
            new OpenApiSecurityValidator()->validate($key, $operation, $schemes),
            new OpenApiReferenceValidator()->validate($key, $contract, $operation),
        );
    }
}
