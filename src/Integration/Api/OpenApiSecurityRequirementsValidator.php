<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiSecurityRequirementsValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @param array<array-key, mixed> $schemes
     * @return list<string>
     */
    public function validate(string $key, array $operation, array $schemes): array
    {
        $errors = [];
        $requirements = is_array($operation['security'] ?? null) ? $operation['security'] : [];
        /** @var list<array<array-key, mixed>> $requirements */
        $requirements = array_values(array_filter($requirements, static fn(mixed $requirement): bool => is_array(
            $requirement,
        )));
        foreach ($requirements as $requirement) {
            foreach (array_keys($requirement) as $scheme) {
                if (($schemes[$scheme] ?? null) !== null) {
                    continue;
                }
                $errors[] = sprintf('%s references unknown security scheme %s.', $key, (string) $scheme);
            }
        }

        $contractErrors = new OpenApiSecurityContractRequirementsValidator()->validate($key, $operation, $requirements);
        foreach ($contractErrors as $error) {
            $errors[] = $error;
        }

        return $errors;
    }
}
