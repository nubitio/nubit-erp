<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityValidator
{
    /** @return list<string> */
    public function validate(string $key, array $operation, array $schemes): array
    {
        $errors = [];
        $requirements = is_array($operation['security'] ?? null) ? $operation['security'] : [];
        foreach ($requirements as $requirement) {
            foreach (array_keys(is_array($requirement) ? $requirement : []) as $scheme) {
                if (($schemes[$scheme] ?? null) === null) {
                    $errors[] = sprintf('%s references unknown security scheme %s.', $key, $scheme);
                }
            }
        }
        foreach ($schemes as $name => $scheme) {
            if (!is_array($scheme) || ($scheme['type'] ?? null) !== 'http' || ($scheme['scheme'] ?? null) !== 'bearer') {
                $errors[] = sprintf('Security scheme %s must be an HTTP bearer scheme.', $name);
            }
        }
        $supported = $schemes['ApiKey']['x-scopes'] ?? [];
        $requiredScopes = is_array($operation['x-required-scopes'] ?? null) ? $operation['x-required-scopes'] : [];
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $supported, true)) {
                $errors[] = sprintf('%s references unsupported scope %s.', $key, $scope);
            }
        }
        if ($requiredScopes !== [] && (($requirements[0]['ApiKey'] ?? null) !== [])) {
            $errors[] = sprintf('%s must use ApiKey with an empty OpenAPI scope list; use x-required-scopes for API-key permissions.', $key);
        }
        if ($requiredScopes === [] && (($requirements[0]['ApiKey'] ?? null) !== null) && (($requirements[0]['ApiKey'] ?? null) !== [])) {
            $errors[] = sprintf('%s must not encode scopes in an HTTP bearer security requirement.', $key);
        }
        if (($operation['x-required-role'] ?? null) !== null && (($requirements[0]['TenantJwt'] ?? null) !== [])) {
            $errors[] = sprintf('%s must use TenantJwt with an empty OpenAPI scope list for role-protected routes.', $key);
        }
        if ($requiredScopes !== [] && (($requirements[0]['ApiKey'] ?? null) === null)) {
            $errors[] = sprintf('%s declares API-key scopes without ApiKey security.', $key);
        }

        return $errors;
    }
}
