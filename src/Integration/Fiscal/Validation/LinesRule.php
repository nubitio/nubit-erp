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
        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                $errors[] = ['field' => "lines[$i]", 'issue' => 'must be an object'];
                continue;
            }
            $description = FieldReader::str($line, 'description');
            if (null === $description || '' === trim($description) || mb_strlen($description) > 250) {
                $errors[] = ['field' => "lines[$i].description", 'issue' => 'is required (max 250 characters)'];
            }
            $quantity = FieldReader::int($line, 'quantity');
            if (null === $quantity || $quantity < 1) {
                $errors[] = ['field' => "lines[$i].quantity", 'issue' => 'must be a positive integer'];
            }
            $amounts = [];
            foreach (['subtotal_minor', 'tax_minor', 'total_minor'] as $field) {
                $value = FieldReader::int($line, $field);
                if (null === $value || $value < 0) {
                    $errors[] = ['field' => "lines[$i].$field", 'issue' => 'must be a non-negative integer (minor units)'];
                    continue;
                }
                $amounts[$field] = $value;
                $sums[$field] += $value;
            }
            if (3 === count($amounts) && $amounts['subtotal_minor'] + $amounts['tax_minor'] !== $amounts['total_minor']) {
                $errors[] = ['field' => "lines[$i].total_minor", 'issue' => 'must equal subtotal_minor + tax_minor'];
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
