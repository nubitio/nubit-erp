<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

use App\Sales\Entity\CollectionReminder;

final readonly class ReminderOutcome
{
    public function __construct(
        public CollectionReminder $reminder,
        /** wa.me link with the message prepared; only for the WhatsApp channel. */
        public ?string $whatsappUrl,
    ) {}
}
