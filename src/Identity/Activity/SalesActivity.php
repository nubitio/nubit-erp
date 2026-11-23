<?php

declare(strict_types=1);

namespace App\Identity\Activity;

/** Writes sales events into the activity record, attributed to the signed-in user. */
final readonly class SalesActivity implements SalesActivityInterface
{
    public function __construct(
        private ActivityRecorderInterface $activity,
    ) {}

    public function saleVoided(string $document, string $total, string $reason): void
    {
        $this->activity->record(
            ActivityType::SALE_VOIDED,
            sprintf('Anuló la venta %s por S/ %s', $document, $total),
            $reason,
            $document,
        );
    }

    public function creditNoteIssued(string $creditNote, ?string $affectedDocument, ?string $reason): void
    {
        $this->activity->record(
            ActivityType::CREDIT_NOTE_ISSUED,
            sprintf('Emitió la nota de crédito %s sobre %s', $creditNote, $affectedDocument ?? 'un comprobante'),
            $reason,
            $creditNote,
        );
    }
}
