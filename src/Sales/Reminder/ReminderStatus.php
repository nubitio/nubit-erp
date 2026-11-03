<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

enum ReminderStatus: string
{
    /** WhatsApp message prepared for the cashier; the server cannot know whether it was sent. */
    case Prepared = 'prepared';
    case Sent = 'sent';
    case Failed = 'failed';
}
