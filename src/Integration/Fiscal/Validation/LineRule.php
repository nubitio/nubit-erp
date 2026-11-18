<?php

declare(strict_types=1);

namespace App\Integration\Fiscal\Validation;

/** Shape of one entry of `lines[]`: description, quantity and a self-consistent set of amounts. */
final class LineRule
{
    private const array AMOUNT_FIELDS = ['subtotal_minor', 'tax_minor', 'total_minor'];

    /**
     * @param array<array-key, mixed> $line
     *
     * @return array{errors: list<array{field: string, issue: string}>, amounts: array<string, int>}
     */
    public function check(int|string $i, array $line): array
    {
        $errors = [];
        $description = FieldReader::str($line, 'description');
        if (null === $description || '' === trim($description) || mb_strlen($description) > 250) {
            $errors[] = ['field' => "lines[$i].description", 'issue' => 'is required (max 250 characters)'];
        }
        $quantity = FieldReader::int($line, 'quantity');
        if (null === $quantity || $quantity < 1) {
            $errors[] = ['field' => "lines[$i].quantity", 'issue' => 'must be a positive integer'];
        }

        $amounts = [];
        foreach (self::AMOUNT_FIELDS as $field) {
            $value = FieldReader::int($line, $field);
            if (null === $value || $value < 0) {
                $errors[] = ['field' => "lines[$i].$field", 'issue' => 'must be a non-negative integer (minor units)'];
                continue;
            }
            $amounts[$field] = $value;
        }
        if (3 === count($amounts) && ($amounts['subtotal_minor'] + $amounts['tax_minor']) !== $amounts['total_minor']) {
            $errors[] = ['field' => "lines[$i].total_minor", 'issue' => 'must equal subtotal_minor + tax_minor'];
        }

        return ['errors' => $errors, 'amounts' => $amounts];
    }
}
