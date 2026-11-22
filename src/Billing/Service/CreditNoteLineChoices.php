<?php

declare(strict_types=1);

namespace App\Billing\Service;

/** Validates the `{lineNumber, quantity, restock}` choices the issue screen sends for drafts and previews. */
final class CreditNoteLineChoices
{
    /**
     * Keeps well-formed rows only; amounts are never trusted from the client.
     *
     * @return list<array{lineNumber: int, quantity: string, restock: bool}>
     */
    public static function normalize(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $choices = [];
        foreach ($raw as $row) {
            if (!is_array($row) || !is_numeric($row['lineNumber'] ?? null)) {
                continue;
            }
            $quantity = (string) ($row['quantity'] ?? '0');
            if (preg_match('/^\d+(\.\d{1,6})?$/', $quantity) !== 1) {
                continue;
            }
            $choices[] = [
                'lineNumber' => (int) $row['lineNumber'],
                'quantity' => $quantity,
                'restock' => ($row['restock'] ?? true) !== false,
            ];
        }

        return $choices;
    }
}
