<?php

declare(strict_types=1);

namespace App\Sales\Reminder;

enum ReminderTone: string
{
    case Friendly = 'friendly';
    case Direct = 'direct';
    case Final = 'final';
}
