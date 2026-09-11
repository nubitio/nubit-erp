<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiContractSemantics
{
    /** @return list<string> */
    public function validate(array $contract): array
    {
        $errors = $this->validateMetadata($contract);
        $validator = new OpenApiOperationValidator();
        $schemes = $contract['components']['securitySchemes'] ?? [];
        foreach (($contract['paths'] ?? []) as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (is_array($item[$method] ?? null)) {
                    $errors = array_merge($errors, $validator->validate($contract, $schemes, $path, $method, $item[$method]));
                }
            }
        }

        return $errors;
    }

    /** @return list<string> */
    private function validateMetadata(array $contract): array
    {
        $errors = [];
        if (($contract['openapi'] ?? null) !== '3.1.0') {
            $errors[] = 'The public contract must use OpenAPI 3.1.0.';
        }
        if (($contract['info']['version'] ?? null) !== '1.0.0') {
            $errors[] = 'The public contract must be version 1.0.0.';
        }
        $schemes = $contract['components']['securitySchemes'] ?? [];
        foreach (['ApiKey', 'TenantJwt'] as $scheme) {
            if (($schemes[$scheme] ?? null) === null) {
                $errors[] = sprintf('Missing security scheme %s.', $scheme);
            }
        }

        return $errors;
    }
}
