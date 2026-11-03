<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

/** A validated reminder request body. */
final readonly class ReminderRequest
{
    public function __construct(
        public string $saleId,
        public ReminderChannel $channel,
        public ReminderTone $tone,
        public ?string $body,
        public bool $force,
    ) {}
}
