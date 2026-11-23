<?php

declare(strict_types=1);

namespace App\Identity\Activity;

/**
 * What the sales side tells the activity record, so the owner can see who voided a sale or issued a
 * credit note. Sensitive by nature, and like the rest of the record it must never break the operation
 * it observes: implementations swallow their own failures.
 */
interface SalesActivityInterface
{
    /** @param string $total the sale total as already formatted, without currency */
    public function saleVoided(string $document, string $total, string $reason): void;

    public function creditNoteIssued(string $creditNote, ?string $affectedDocument, ?string $reason): void;
}
