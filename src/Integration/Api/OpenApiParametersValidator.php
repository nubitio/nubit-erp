<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiParametersValidator
{
    /**
     * @param array<array-key, mixed> $operation
     * @return list<string>
     */
    public function validate(string $key, string $path, array $operation): array
    {
        $matches = [];
        preg_match_all('/\{([^}]+)}/', $path, $matches);
        $parameters = is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [];
        $errors = [];
        foreach ($matches[1] ?? [] as $name) {
            if ($this->hasRequiredParameter($parameters, $name)) {
                continue;
            }
            $errors[] = sprintf('%s must declare required path parameter %s.', $key, $name);
        }

        return $errors;
    }

    /** @param array<array-key, mixed> $parameters */
    private function hasRequiredParameter(array $parameters, string $name): bool
    {
        /** @var list<array<array-key, mixed>> $typedParameters */
        $typedParameters = array_values(array_filter($parameters, static fn(mixed $parameter): bool => is_array(
            $parameter,
        )));
        foreach ($typedParameters as $parameter) {
            if (($parameter['name'] ?? null) === $name && ($parameter['required'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}
