<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiContractSemantics
{
    /**
     * @param array<string, mixed> $contract
     * @return list<string>
     */
    public function validate(array $contract): array
    {
        $errors = $this->validateMetadata($contract);
        $validator = new OpenApiOperationValidator();
        $schemes = is_array($contract['components']['securitySchemes'] ?? null)
            ? $contract['components']['securitySchemes']
            : [];
        $paths = is_array($contract['paths'] ?? null) ? $contract['paths'] : [];
        foreach ($paths as $path => $item) {
            if (!is_string($path) || !is_array($item)) {
                continue;
            }
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $operation = $item[$method] ?? null;
                if (is_array($operation)) {
                    $errors = array_merge($errors, $validator->validate(
                        $contract,
                        $schemes,
                        $path,
                        $method,
                        $operation,
                    ));
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $contract
     * @return list<string>
     */
    private function validateMetadata(array $contract): array
    {
        $errors = [];
        if (($contract['openapi'] ?? null) !== '3.1.0') {
            $errors[] = 'The public contract must use OpenAPI 3.1.0.';
        }
        if (($contract['info']['version'] ?? null) !== '1.0.0') {
            $errors[] = 'The public contract must be version 1.0.0.';
        }
        $schemes = is_array($contract['components']['securitySchemes'] ?? null)
            ? $contract['components']['securitySchemes']
            : [];
        foreach (['ApiKey', 'TenantJwt'] as $scheme) {
            if (($schemes[$scheme] ?? null) !== null) {
                continue;
            }
            $errors[] = sprintf('Missing security scheme %s.', $scheme);
        }

        return $errors;
    }
}
