<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityRequirementsValidator
{
    /** @return list<string> */
    public function validate(string $key, array $operation, array $schemes): array
    {
        $errors = [];
        $requirements = is_array($operation['security'] ?? null) ? $operation['security'] : [];
        foreach ($requirements as $requirement) {
            foreach (array_keys(is_array($requirement) ? $requirement : []) as $scheme) {
                if (($schemes[$scheme] ?? null) !== null) {
                    continue;
                }
                $errors[] = sprintf('%s references unknown security scheme %s.', $key, $scheme);
            }
        }

        $errors = array_merge($errors, new OpenApiSecurityContractRequirementsValidator()->validate(
            $key,
            $operation,
            $requirements,
        ));

        return $errors;
    }
}
