<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use DateTimeImmutable;

/** Everything a reminder message needs, already resolved from the sale and the company. */
final readonly class ReminderContext
{
    public function __construct(
        public string $companyName,
        public string $contactName,
        /** Full voucher number such as F001-00000262; null when the sale has no voucher. */
        public ?string $documentNumber,
        /** Money already formatted for reading, e.g. "S/ 845.00". */
        public string $amount,
        public ?DateTimeImmutable $dueDate,
        public int $daysOverdue,
        public ?string $documentUrl,
    ) {}
}
