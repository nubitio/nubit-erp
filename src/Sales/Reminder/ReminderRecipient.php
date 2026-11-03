<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

/** Who a reminder goes to: a normalized WhatsApp number or an e-mail address, never both. */
final readonly class ReminderRecipient
{
    public function __construct(
        public ?string $phone,
        public ?string $email,
    ) {}
}
