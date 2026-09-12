<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityRequirementsValidator
{
    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $schemes
     * @return list<string>
     */
    public function validate(string $key, array $operation, array $schemes): array
    {
        /** @var list<string> $errors */
        $errors = [];
        $requirements = is_array($operation['security'] ?? null) ? $operation['security'] : [];
        foreach ($requirements as $requirement) {
            foreach (array_keys(is_array($requirement) ? $requirement : []) as $scheme) {
                if (($schemes[$scheme] ?? null) !== null) {
                    continue;
                }
                $errors[] = sprintf('%s references unknown security scheme %s.', $key, (string) $scheme);
            }
        }

        /** @var list<string> $contractErrors */
        $contractErrors = new OpenApiSecurityContractRequirementsValidator()->validate($key, $operation, $requirements);
        foreach ($contractErrors as $error) {
            $errors[] = $error;
        }

        return $errors;
    }
}
