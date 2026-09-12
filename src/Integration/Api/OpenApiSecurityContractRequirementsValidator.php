<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityContractRequirementsValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @param array<array-key, mixed> $requirements
     * @return list<string>
     */
    public function validate(string $key, array $operation, array $requirements): array
    {
        $requiredScopes = is_array($operation['x-required-scopes'] ?? null) ? $operation['x-required-scopes'] : [];
        $firstRequirement = is_array($requirements[0] ?? null) ? $requirements[0] : [];
        $apiKeyRequirement = $firstRequirement['ApiKey'] ?? null;
        $tenantJwtRequirement = $firstRequirement['TenantJwt'] ?? null;
        $errors = [];
        if ($requiredScopes !== [] && $apiKeyRequirement !== []) {
            $errors[] = sprintf(
                '%s must use ApiKey with an empty OpenAPI scope list; use x-required-scopes for API-key permissions.',
                $key,
            );
        }
        if ($requiredScopes === [] && $apiKeyRequirement !== null && $apiKeyRequirement !== []) {
            $errors[] = sprintf('%s must not encode scopes in an HTTP bearer security requirement.', $key);
        }
        if (($operation['x-required-role'] ?? null) !== null && $tenantJwtRequirement !== []) {
            $errors[] = sprintf(
                '%s must use TenantJwt with an empty OpenAPI scope list for role-protected routes.',
                $key,
            );
        }
        if ($requiredScopes !== [] && $apiKeyRequirement === null) {
            $errors[] = sprintf('%s declares API-key scopes without ApiKey security.', $key);
        }

        return $errors;
    }
}
