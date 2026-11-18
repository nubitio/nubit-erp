<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/**
 * Optional `lines[]`: when present, every line must be well formed and the
 * lines must sum to the document's subtotal, tax and total exactly.
 */
final class LinesRule implements DocumentRule
{
    public const int MAX_LINES = 100;

    public function errors(array $body): array
    {
        if (!array_key_exists('lines', $body) || null === $body['lines']) {
            return [];
        }
        $lines = $body['lines'];
        if (!is_array($lines) || !array_is_list($lines) || [] === $lines || count($lines) > self::MAX_LINES) {
            return [['field' => 'lines', 'issue' => 'must be a list of 1 to ' . self::MAX_LINES . ' lines']];
        }

        $errors = [];
        $sums = ['subtotal_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0];
        $lineRule = new LineRule();
        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                $errors[] = ['field' => "lines[$i]", 'issue' => 'must be an object'];
                continue;
            }
            $checked = $lineRule->check($i, $line);
            $errors = [...$errors, ...$checked['errors']];
            foreach ($checked['amounts'] as $field => $value) {
                $sums[$field] += $value;
            }
        }

        if ([] === $errors) {
            foreach ($sums as $field => $sum) {
                if (FieldReader::int($body, $field) !== $sum) {
                    $errors[] = ['field' => 'lines', 'issue' => "lines must sum to $field"];
                }
            }
        }

        return $errors;
    }
}
