<?php

declare(strict_types=1);

namespace App\Integration\Api;

final class OpenApiReferenceValidator
{
    /** @return list<string> */
    public function validate(string $key, array $contract, array $operation): array
    {
        $errors = [];
        foreach (($operation['responses'] ?? []) as $response) {
            $reference = is_array($response) ? ($response['$ref'] ?? null) : null;
            if (is_string($reference) && !$this->exists($contract, $reference)) {
                $errors[] = sprintf('%s references missing response %s.', $key, $reference);
            }
        }

        return $errors;
    }

    private function exists(array $document, string $reference): bool
    {
        if (!str_starts_with($reference, '#/')) {
            return false;
        }
        $value = $document;
        foreach (explode('/', substr($reference, 2)) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return false;
            }
            $value = $value[$part];
        }

        return true;
    }
}
