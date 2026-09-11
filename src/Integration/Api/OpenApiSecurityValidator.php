<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityValidator
{
    /** @return list<string> */
    public function validate(string $key, array $operation, array $schemes): array
    {
        $errors = [];
        foreach ($operation['security'] ?? [] as $requirement) {
            foreach (array_keys(is_array($requirement) ? $requirement : []) as $scheme) {
                if (($schemes[$scheme] ?? null) === null) {
                    $errors[] = sprintf('%s references unknown security scheme %s.', $key, $scheme);
                }
            }
        }
        $supported = $schemes['ApiKey']['x-scopes'] ?? [];
        foreach ($operation['x-required-scopes'] ?? [] as $scope) {
            if (!in_array($scope, $supported, true)) {
                $errors[] = sprintf('%s references unsupported scope %s.', $key, $scope);
            }
        }
        if (($operation['x-required-scopes'] ?? []) !== [] && (($operation['security'][0]['ApiKey'] ?? null) === null)) {
            $errors[] = sprintf('%s declares API-key scopes without ApiKey security.', $key);
        }

        return $errors;
    }
}
