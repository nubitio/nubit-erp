<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

enum ReminderChannel: string
{
    /** The cashier opens WhatsApp with the message ready; nothing is sent from the server. */
    case Whatsapp = 'whatsapp';
    case Email = 'email';
}
